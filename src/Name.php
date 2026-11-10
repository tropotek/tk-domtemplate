<?php
namespace Tk\DomTemplate;

/**
 * A parsed var attribute value: the bare name and its kind
 */
final readonly class Name
{
    public function __construct(
        public string $name,
        public Kind $kind,
    ) {}

    /**
     * @param string $label where the value came from, used in error messages
     * @throws TemplateException when the value is not a valid name
     */
    public static function parse(string $value, string $label): self
    {
        if (!preg_match('/^([A-Za-z0-9_.-]+)(\?|\[\])?$/', trim($value), $m)) {
            throw new TemplateException(sprintf('Invalid var name `%s` in %s', $value, $label));
        }
        $kind = match ($m[2] ?? '') {
            '?' => Kind::Optional,
            '[]' => Kind::Repeat,
            default => Kind::Var,
        };
        return new self($m[1], $kind);
    }
}
