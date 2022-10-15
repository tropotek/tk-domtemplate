<?php
namespace Dom;

/**
 * Class Exception
 *
 * @author Michael Mifsud <http://www.tropotek.com/>
 * @see http://www.tropotek.com/
 * @license Copyright 2007 Michael Mifsud
 */
class Exception extends \Exception {


    /**
     * @var string
     */
    protected $dump = '';

    /**
     * Construct the exception. Note: The message is NOT binary safe.
     * @see http://php.net/manual/en/exception.construct.php
     * @param string $message [optional] The Exception message to throw.
     * @param int $code [optional] The Exception code.
     * @param \Exception $previous [optional] The previous exception used for the exception chaining. Since 5.3.0
     * @param string $dump
     * @since 5.1.0
     */
    public function __construct($message = "", $code = 0, $previous = null, $dump = '') {
        parent::__construct($message, $code);
        $this->dump = $dump;

    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * String representation of the exception
     * @see http://php.net/manual/en/exception.tostring.php
     * @return string the string representation of the exception.
     */
    public function __toString()
    {
        $str = parent::__toString();
        if ($this->dump != null) {
            $str =  "DOM Errors:\n" . $this->dump . "\n\n" . $str;
        }
        return $str;
    }

    public function getAsString(): string
    {
        return $this->__toString();
    }
}