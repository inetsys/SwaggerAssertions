<?php

namespace FR3D\SwaggerAssertions\PhpUnit;

use FR3D\SwaggerAssertions\SchemaManager;
use PHPUnit_Framework_Constraint as Constraint;

/**
 * Validate request body match against defined Swagger request parameters.
 */
class RequestParametersConstraint extends Constraint
{
    /**
     * @var SchemaManager
     */
    protected $schemaManager;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $httpMethod;

    protected $lastError;

    /**
     * @param SchemaManager $schemaManager
     * @param string $path Swagger path template.
     * @param string $httpMethod
     */
    public function __construct(SchemaManager $schemaManager, $path, $httpMethod)
    {
        parent::__construct();

        $this->schemaManager = $schemaManager;
        $this->path = $path;
        $this->httpMethod = $httpMethod;
    }

    /**
     * Matcher for constraint
     * @param  stdClass $other Guzzle Request object
     * @return boolean
     */
    protected function matches($other)
    {
        $parameters = $this->schemaManager->getPath([
            'paths',
            $this->path,
            $this->httpMethod,
            'parameters'
        ]);
        $fields = $other->getBody()->getFields();
        $keys = [];
        foreach($parameters as $param) {
            // 1. Search for required fields
            if ($param->required && !isset($fields[$param->name])) {
                $this->lastError = 'Field "'.$param->name.'" required by Schema is not present';
                return false;
            }
            $keys[] = $param->name;
            // 2. Check for value type
            switch($param->type) {
                case 'integer':
                    if (!is_numeric($fields[$param->name])) {
                        $this->lastError = 'Value for field "'.$param->name.'" is not an integer';
                        return false;
                    }
                    break;
                case 'string':
            }
        }
        // 3. Search for unexpected fields
        $diff = array_diff(array_keys($fields), $keys);
        if (!empty($diff)) {
            $this->lastError = 'Fields '.json_encode(array_values($diff)).' present in request are not expected according to Schema';
            return false;
        }

        return true;
    }

    protected function failureDescription($other)
    {
        return 'request ' . json_encode($other->getBody()->getFields()) . ' ' . $this->toString();
    }

    protected function additionalFailureDescription($other)
    {
         return $this->lastError;
    }

    public function toString()
    {
        return 'is valid';
    }

}
