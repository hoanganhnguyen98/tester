<?php

namespace NinjaTester\Validator;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Validator
{
    CONST PREFIX_MESSAGE = 'validate.message.';
    CONST FILE_FIELDS = ['image', 'imageArray', 'file'];
    CONST REQUIRED_FUNCTION = 'required';

    protected $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    private function checkExist($request, $field, $isFile)
    {
        if ($request->getMethod() === 'POST' ) {
            return $isFile ? $request->files->has($field) : $request->request->has($field);
        }

        return $request->query->has($field);
    }

    /**
     * Return an error response.
     * 
     * @param array $parameters
     * @param Request $request
     *  
     */
    public function validate(array $parameters, $request)
    {
        $errorMessages = null;

        $constraintsArray = [];
        $violationsArray = [];

        foreach ($parameters as $field => $param) {
            $violationsArray[$field] = !is_array($request->get($field)) ? trim($request->get($field)) : $request->get($field);
            $constraintsArray[$field] = [];
            $arrayParam = explode('|', $param);

            foreach ($arrayParam as $node) {
                $arrNode = explode(':', $node); 
                $functionName = $arrNode[0];

                $isFile = false;
                if (in_array($functionName, self::FILE_FIELDS)) {
                    $isFile = true;
                    $violationsArray[$field] = $request->files->get($field);
                }

                $addToValidator = false;
                // nếu field CÓ truyền lên
                if ($this->checkExist($request, $field, $isFile)) {
                    $addToValidator = true;
                } else {
                    // KHÔNG truyền lên nhưng CÓ điều kiện required
                    if (in_array(self::REQUIRED_FUNCTION, $arrayParam)) {
                        $addToValidator = true;
                    }
                }

                if ($addToValidator && method_exists($this, $functionName)) {
                    $params = isset($arrNode[1]) ? $arrNode[1] : null;

                    array_push($constraintsArray[$field], $this->$functionName($field, $params));   
                } 
            }
        }

        $constraints = new Assert\Collection(['fields' => $constraintsArray]);
        $violations = $this->validator->validate($violationsArray, $constraints);

        if (count($violations) > 0) {
            $errorMessages = [];
            $accessor = PropertyAccess::createPropertyAccessor();

            foreach ($violations as $violation) {
                $message = $violation->getMessage();

                $accessor->setValue($errorMessages, $violation->getPropertyPath(), $message);
            }
        }

        return $errorMessages;
    }

    /**
     * Return first error message.
     *
     * @param array $errorMessages
     *  
     */
    public function getError(array $errorMessages)
    {
        if (!empty($errorMessages)) {
            foreach ($errorMessages as $field => $message) {
                return [
                    'message' => $message,
                    'field' => $field
                ];
            }
        }

        return '';
    }

    /**
     * Format: 'required'
     */
    function required($key, $params = null)
    {
        $options = [
            "message" => self::PREFIX_MESSAGE . __FUNCTION__,
        ];

        return new Assert\NotBlank($options);
    }

    /**
     * Format: 'positive'
     */
    function positive($key, $params = null)
    {
        $options = [
            "message" => self::PREFIX_MESSAGE . __FUNCTION__,
        ];

        return new Assert\Positive($options);
    }

    /**
     * Format: 'date'
     */
    function date($key, $params = null)
    {
        $options = [
            "message" => self::PREFIX_MESSAGE . __FUNCTION__,
        ];

        return new Assert\Date($options);
    }

    /**
     * Format: 'datetime'
     */
    function datetime($key, $params = null)
    {
        $options = [
            "message" => self::PREFIX_MESSAGE . __FUNCTION__,
        ];

        return new Assert\DateTime($options);
    }

    /**
     * Format: 'email'
     */
    function email($key, $params = null)
    {
        $options = [
            "message" => self::PREFIX_MESSAGE . __FUNCTION__,
            "mode" => "strict"
        ];

        return new Assert\Email($options);
    }

    /**
     * Check if string consists of all letters or digits (azAZ09)
     * Format: 'alnum'
     */
    function alnum($key)
    {
        return $this->type(__FUNCTION__);
    }

    /**
     * Format: 'array'
     */
    function array($key)
    {
        return $this->type(__FUNCTION__);
    }

    /**
     * Format: 'numeric'
     */
    function numeric($key, $params = null)
    {
        return $this->type(__FUNCTION__);
    }

    /**
     * Format: 'string'
     */
    function string($key)
    {
        return $this->type(__FUNCTION__);
    }

    /**
     * Check type of input
     */
    function type($type, $function = null)
    {
        $function = $function ?? $type;

        $options = [
            "type" => $type,
            "message" => self::PREFIX_MESSAGE . $function,
        ];

        return new Assert\Type($options);
    }

    /**
     * Format: 'choice:<option1>,<option2>'
     * Example: 'choice:desc,asc'
     */
    function choice($key, $params = null)
    {
        $options = [
            "message" => self::PREFIX_MESSAGE . __FUNCTION__,
        ];
        
        if ($params) {
            $arrParam = explode(',', $params);
            $options["choices"] = $arrParam;
        }
        
        return new Assert\Choice($options);
    }



    /**
     * Format: 'lessthan:<value>'
     */
    function lessthan($key, $params = null)
    {
        $options = [
            "message" => self::PREFIX_MESSAGE . __FUNCTION__,
        ];
        
        if ($params) {
            $options["value"] = $params;
        }
        
        return new Assert\LessThan($options);
    }

    /**
     * Count elements of an array. Input value must be type of array.
     * Format: 'count:min,1,max,5'
     */
    function count($key, $params = null)
    {
        $options = [
            "minMessage" => self::PREFIX_MESSAGE . __FUNCTION__ . '.min',
            "maxMessage" => self::PREFIX_MESSAGE . __FUNCTION__ . '.max',
            "exactMessage" => self::PREFIX_MESSAGE . __FUNCTION__ . '.exact',
        ];

        if ($params) {
            $arrParam = explode(',', $params);

            foreach ($arrParam as $key => $value) {
                if (($key % 2) == 0) {
                    $options[$value] = (int) $arrParam[($key + 1)];
                }
            }
        }

        return new Assert\Count($options);
    }

    /**
     * Format: 'file:maxSize,5M,mimeTypes,image/jpeg#image/png'
     */
    function file($key, $params = null)
    {
        $options = [
            "maxSizeMessage" => self::PREFIX_MESSAGE . __FUNCTION__ .'.maxSize',
            "mimeTypesMessage" => self::PREFIX_MESSAGE . __FUNCTION__ .'.mimeTypes',
        ];

        if ($params) {
            $arrParam = explode(',', $params);

            foreach ($arrParam as $key => $value) {
                if (($key % 2) == 0) {

                    if ($value == "mimeTypes") {
                        $options[$value] = explode("#", $arrParam[($key + 1)]);
                    } else {
                        $options[$value] = $arrParam[($key + 1)];
                    }
                }
            }
        }

        return new Assert\File($options);
    }

    /**
     * Format: 'image:maxSize,5M,mimeTypes,image/jpeg#image/png'
     */
    function image($key, $params = null)
    {
        $options = [
            "maxSizeMessage" => self::PREFIX_MESSAGE . __FUNCTION__ .'.maxSize',
            "mimeTypesMessage" => self::PREFIX_MESSAGE . __FUNCTION__ .'.mimeTypes',
        ];

        if ($params) {
            $arrParam = explode(',', $params);

            foreach ($arrParam as $key => $value) {
                if (($key % 2) == 0) {

                    if ($value == "mimeTypes") {
                        $options[$value] = explode("#", $arrParam[($key + 1)]);
                    } else {
                        $options[$value] = $arrParam[($key + 1)];
                    }
                }
            }
        }

        return new Assert\Image($options);
    }

    function imageArray($key, $params = null)
    {
        $options = [
            "maxSizeMessage" => self::PREFIX_MESSAGE . __FUNCTION__ .'.maxSize',
            "mimeTypesMessage" => self::PREFIX_MESSAGE . __FUNCTION__ .'.mimeTypes',
        ];

        if ($params) {
            $arrParam = explode(',', $params);

            foreach ($arrParam as $key => $value) {
                if (($key % 2) == 0) {

                    if ($value == "mimeTypes") {
                        $options[$value] = explode("#", $arrParam[($key + 1)]);
                    } else {
                        $options[$value] = $arrParam[($key + 1)];
                    }
                }
            }
        }

        return new Assert\All([new Assert\Image($options)]);
    }

    /**
     * Format: 'length:min,1,max,5'
     */
    function length($key, $params = null)
    {
        $options = [
            "minMessage" => self::PREFIX_MESSAGE . __FUNCTION__ .'min',
            "maxMessage" => self::PREFIX_MESSAGE . __FUNCTION__ .'max'
        ];
        
        if ($params) {
            $arrParam = explode(',', $params);

            foreach ($arrParam as $key => $value) {
                if (($key % 2) == 0) {
                    $options[$value] = $arrParam[($key + 1)];
                }
            }
        }

        return new Assert\Length($options);
    }

    /**
     * Check if input == value
     * Format: 'confirm:<value>'
     * Example: confirm:123abc
     */
    function confirm($key, $params)
    {
        $arrParam = explode(',', $params);

        $options = [
            'message' => self::PREFIX_MESSAGE . __FUNCTION__,
            'value' => $arrParam[0]
        ];

        return new Assert\EqualTo($options);
    }

    /**
     * Check if value valid with regex expression
     * Format: 'regex:expression'
     */
    function regex($key, $expression = null) {
        $options = [
            'message' => self::PREFIX_MESSAGE . __FUNCTION__,
            'pattern' => '/'. $expression . '/'
        ];

        return new Assert\Regex($options);
    }
}
