<?php

namespace Mtrajano\LaravelSwagger;

use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Str;
use Illuminate\Support\Pluralizer;
use Illuminate\Routing\Route;
use Illuminate\Foundation\Http\FormRequest;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types;
use phpDocumentor\Reflection\Type;

class Generator
{
    protected $config;

    protected $routeFilter;

    protected $docs;

    protected $uri;

    protected $originalUri;

    protected $method;

    protected $action;

    protected $preset;

    protected const TYPES = [
        Types\Array_::class => 'array',
        Types\Boolean::class => 'boolean',
        Types\Callable_::class => 'object',
        Types\Collection::class => 'array',
        Types\Float_::class => 'number',
        Types\Integer::class => 'number',
        Types\Mixed_::class => 'object',
        Types\Null_::class => 'object',
        Types\String_::class => 'string',
        Types\Object_::class => 'object',
    ];

    public function __construct($config, $routeFilter = null, $preset = 'default')
    {
        $this->config = $config;
        $this->routeFilter = $routeFilter;
        $this->preset = $preset;
        $this->docParser = DocBlockFactory::createInstance();
    }

    public function generate()
    {
        $this->docs = $this->getBaseInfo();

        foreach ($this->getAppRoutes() as $route) {
            $this->originalUri = $uri = $this->getRouteUri($route);
            $this->uri = strip_optional_char($uri);

            if ($this->routeFilter && !preg_match('/^' . preg_quote($this->routeFilter, '/') . '/', $this->uri)) {
                continue;
            }

            $this->action = $route->getAction()['uses'];
            if(in_array($this->action, $this->config['presets'][$this->preset]['ignored_controllers'])) {
                continue;
            }

            $methods = $route->methods();

            if (!isset($this->docs['paths'][$this->uri])) {
                $this->docs['paths'][$this->uri] = [];
            }

            foreach ($methods as $method) {
                $this->method = strtolower($method);

                if (in_array($this->method, $this->config['ignoredMethods'])) continue;

                $this->generatePath();
            }
        }

        return $this->docs;
    }

    protected function getBaseInfo()
    {
        $baseInfo = [
            'swagger' => '2.0',
            'info' => [
                'title' => $this->config['title'],
                'description' => $this->config['description'],
                'version' => $this->config['appVersion'],
            ],
            'host' => $this->config['host'],
            'basePath' => $this->config['basePath'],
        ];

        if (!empty($this->config['schemes'])) {
            $baseInfo['schemes'] = $this->config['schemes'];
        }

        if (!empty($this->config['consumes'])) {
            $baseInfo['consumes'] = $this->config['consumes'];
        }

        if (!empty($this->config['produces'])) {
            $baseInfo['produces'] = $this->config['produces'];
        }

        $baseInfo['paths'] = [];

        return $baseInfo;
    }

    protected function getAppRoutes()
    {
        return app('router')->getRoutes();
    }

    protected function getRouteUri(Route $route)
    {
        $uri = $route->uri();

        if (!Str::startsWith($uri, '/')) {
            $uri = '/' . $uri;
        }

        return $uri;
    }

    protected function generatePath()
    {
        $actionInstance = is_string($this->action) ? $this->getActionClassInstance($this->action) : null;
        $docBlock = $actionInstance ? ($actionInstance->getDocComment() ?: "") : "";

        list($isDeprecated, $summary, $description, $responses) = $this->parseActionDocBlock($docBlock);

        $this->docs['paths'][$this->uri][$this->method] = [
            'summary' => $summary,
            'description' => $description,
            'deprecated' => $isDeprecated,
            'responses' => $responses,
        ];

        $this->addTagParameters();
        $this->addModel($this->getModelName());
        $this->addActionParameters();
    }

    protected function addActionParameters()
    {
        $rules = $this->getFormRules() ?: [];

        $parameters = (new Parameters\PathParameterGenerator($this->originalUri))->getParameters();

        if (!empty($rules)) {
            $parameterGenerator = $this->getParameterGenerator($rules);

            $parameters = array_merge($parameters, $parameterGenerator->getParameters());
        }

        if (!empty($parameters)) {
            $this->docs['paths'][$this->uri][$this->method]['parameters'] = $parameters;
        }
    }

    protected function getRouteModelName()
    {
        if($this->config['guess_tag']) {
            $str = Str::replaceFirst($this->routeFilter, '', $this->uri);
            return Pluralizer::singular(explode('/', $str)[1]);
        }

        return 'Generic';
    }

    protected function getModelName()
    {
        $match = [];
        preg_match('/{(\w*)}/', $this->uri, $match);
        return ucfirst($match[1] ?? $this->getRouteModelName());
    }

    protected function determineType(Type $type)
    {
        return self::TYPES[get_class($type)];
    }

    protected function getModelFields(array $rawFields)
    {
        $fields = [];
        foreach($rawFields as $field) {
            $name = $field->getVariableName();
            $type = $field->getType();
            if($type instanceof Compound) {
                $fields[$name] = ['type' => $this->determineType($type->get(0))];
                continue;
            }
            $fields[$name] = ['type' => $this->determineType($type)];
        }

        return $fields;
    }

    protected function parseModelComment(string $tag, string $docBlock)
    {
        if(empty($docBlock)) {
            return [
                'title' => $tag,
                'description' => "{$tag} model",
            ];
        }

        $parsedComment = $this->docParser->create($docBlock);
        return [
            'title' => $tag,
            'description' => "{$tag} model",
            'properties' => $this->getModelFields($parsedComment->getTagsByName('property')),
        ];
    }

    protected function addModel(string $tag)
    {
        if(class_exists("{$this->config['model_namespace']}{$tag}")) {
            $model = new ReflectionClass("{$this->config['model_namespace']}{$tag}");
            $params = $this->parseModelComment($tag, $model->getDocComment());
            $this->docs['definitions'][$tag] = $params;
        }
    }

    protected function addTagParameters()
    {
        $this->docs['paths'][$this->uri][$this->method]['tags'] = [$this->getModelName()];
    }

    protected function getFormRules()
    {
        if (!is_string($this->action)) return false;

        $parameters = $this->getActionClassInstance($this->action)->getParameters();

        foreach ($parameters as $parameter) {
            $class = (string) $parameter->getType();

            if (is_subclass_of($class, FormRequest::class)) {
                return (new $class)->rules();
            }
        }
    }

    protected function getParameterGenerator($rules)
    {
        switch ($this->method) {
            case 'post':
            case 'put':
            case 'patch':
                return new Parameters\BodyParameterGenerator($rules);
            default:
                return new Parameters\QueryParameterGenerator($rules);
        }
    }

    protected function processResponses(array $rawResponses)
    {
        $responses = [];
        foreach($rawResponses as $response) {
            $split = explode(' ', $response->getDescription(), 2);
            $statusCode = $split[0];
            $responses[$statusCode] = ['description' => $split[1]];
        }

        $responses = $responses + $this->config['baseResponses'];
        if($this->modResponsable()) {
            $responses = $responses + $this->config['modResponses'];
        }

        return $responses;
    }

    protected function getDefaultValues()
    {
        return [false, "", "", $this->config['baseResponses']];
    }

    protected function modResponsable()
    {
        return in_array($this->method, [
            'post',
            'put',
            'patch',
            'delete',
        ]);
    }

    private function getActionClassInstance(string $action)
    {
        list($class, $method) = Str::parseCallback($action);

        return new ReflectionMethod($class, $method);
    }

    private function parseActionDocBlock(string $docBlock)
    {
        if (empty($docBlock) || !$this->config['parseDocBlock']) {
            return $this->getDefaultValues();
        }

        try {
            $parsedComment = $this->docParser->create($docBlock);

            $isDeprecated = $parsedComment->hasTag('deprecated');

            $summary = $parsedComment->getSummary();
            $description = (string) $parsedComment->getDescription();

            $responses = $this->processResponses($parsedComment->getTagsByName('response'));

            return [$isDeprecated, $summary, $description, $responses];
        } catch(\Exception $e) {
            return $this->getDefaultValues();
        }
    }
}