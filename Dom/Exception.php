<?php
namespace Dom;

/**
 * @author Tropotek <http://www.tropotek.com/>
 */
class Exception extends \Exception {

    protected string $dump = '';

    /**
     * Construct the exception. Note: The message is NOT binary safe.
     * @see http://php.net/manual/en/exception.construct.php
     * @param string $message [optional] The Exception message to throw.
     * @param int $code [optional] The Exception code.
     * @param \Exception $previous [optional] The previous exception used for the exception chaining. Since 5.3.0
     * @param string $dump
     * @since 5.1.0
     */
    public function __construct(string $message = '', int $code = 0, ?\Exception $previous = null, string $dump = '') {
        parent::__construct($message, $code);
        $this->dump = $dump;
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * String representation of the exception
     * @see http://php.net/manual/en/exception.tostring.php
     * @return string the string representation of the exception.
     */
    public function __toString(): string
    {
        $str = parent::__toString();
        if (!empty($this->dump)) {
            $str =  "DOM Errors:\n" . $this->dump . "\n\n" . $str;
        }
        return $str;
    }

    public function getAsString(): string
    {
        return $this->__toString();
    }
}