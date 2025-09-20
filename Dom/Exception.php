<?php
namespace Dom;

/**
 * @author Tropotek <http://www.tropotek.com/>
 */
class Exception extends \Exception {

    protected string $dump = '';

    public function __construct(string $message = '', int $code = 0, ?\Exception $previous = null, string $dump = '') {
        parent::__construct($message, $code, $previous);
        $this->dump = $dump;
    }

    public function __toString(): string
    {
        $str = parent::__toString();
        if (!empty($this->dump)) {
            $str =  "DOM Errors:\n" . $this->dump . "\n\n" . $str;
        }
        return $str;
    }
}