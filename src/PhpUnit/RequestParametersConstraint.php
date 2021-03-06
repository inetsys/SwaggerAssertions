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
        $parameters = $this->schemaManager->getRequestParameters($this->path, $this->httpMethod);

        $body = $other->getBody();
        $fieldsInBody = $body !== null ? $body->getFields() : [];

        $qs = $other->getQuery();
        $fieldsInQS = $qs !== null ? $qs->toArray() : [];

        $keys = [];
        foreach($parameters as $param) {
            if ($param->in == 'formData') {
                if ($param->type == 'file')
                    $fieldToCheck = $body->getFile($param->name);
                else
                    $fieldToCheck = array_key_exists($param->name, $fieldsInBody) ? $fieldsInBody[$param->name] : null;
            }
            elseif ($param->in == 'query') {
                $fieldToCheck = array_key_exists($param->name, $fieldsInQS) ? $fieldsInQS[$param->name] : null;
            }
            else {
                continue; // probably in path
            }
            // 1. Search for required fields
            if (isset($param->required) && $param->required && !isset($fieldToCheck)) {
                $this->lastError = 'Field "'.$param->name.'" required by Schema is not present';
                return false;
            }
            $keys[] = $param->name;
            // 2. Check for value type
            if (!empty($fieldToCheck)) {
                switch($param->type) {
                    case 'integer':
                        if (!is_numeric($fieldToCheck)) {
                            $this->lastError = 'Value for field "'.$param->name.'" is not an integer';
                            return false;
                        }
                        break;
                    case 'boolean':
                        if (is_string($fieldToCheck)) {
                            $this->lastError = 'Value for boolean field "'.$param->name.'" cannot be a string';
                            return false;
                        }
                    case 'string':
                }
            }
        }

        // 3. Search for unexpected fields
        $fields = array_merge($fieldsInBody, $fieldsInQS);
        $diff = array_diff(array_keys($fields), $keys);
        if (!empty($diff)) {
            $this->lastError = 'Fields '.json_encode(array_values($diff)).' present in request are not expected according to Schema';
            return false;
        }

        return true;
    }

    /**
     * Check field list against Schema
     * @param  array $fields List of fields from body, querystring, etc.
     * @return boolean
     */
    protected function checkFields($fields) {

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

