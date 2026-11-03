<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Render;

/**
 * The document attributes every published page is rendered with.
 *
 * The policy exists so a page cannot inherit the converter's document chrome by accident: the
 * stylesheet, the embedded stylesheet, the built-in table of contents, docinfo files, and the
 * syntax highlighter are all switched off, because the site shell owns every one of them.
 *
 * `noembed` and `linkcss` are both set even though `SiteConverter::renderStylesheet()` returns an
 * empty string. They are the second and third defences behind it: if a future converter release
 * emitted a stylesheet anyway, the attributes force it to be a link rather than an inline `<style>`
 * block, which is the only delivery form the multi-repo output allows.
 *
 * The six clock attributes are pinned to an empty string. The converter seeds them from `date()` at
 * document construction, so leaving them alone would put the build date into every page and make
 * two builds of the same sources differ. Setting them to an empty string keeps them "set" for the
 * converter (it uses `hasAttribute()` to decide whether to emit a footer line) while emitting no
 * value at all.
 *
 * @api
 */
final readonly class AttributePolicy
{
    /**
     * The attributes the converter seeds from the wall clock.
     *
     * @var list<string>
     */
    public const array CLOCK_ATTRIBUTES = [
        'docdate',
        'doctime',
        'docdatetime',
        'localdate',
        'localtime',
        'localdatetime',
    ];

    public function __construct(
        private bool $syntaxHighlighting = false,
    ) {}

    /**
     * The `attributes` option for the converter.
     *
     * `false` unsets an attribute, which is the array form of the AsciiDoc `name!` syntax. The
     * highlighter is only unset when highlighting is off: when the site enables it the attribute is
     * left alone so the configured highlighter keeps whatever default the converter gives it.
     *
     * @return array<string, string|false>
     */
    public function attributes(): array
    {
        $attributes = [
            'stylesheet' => false,
            'noembed'    => '',
            'linkcss'    => '',
            'toc'        => false,
            'docinfo'    => false,
        ];

        if (! $this->syntaxHighlighting) {
            $attributes['source-highlighter'] = false;
        }

        foreach (self::CLOCK_ATTRIBUTES as $name) {
            $attributes[$name] = '';
        }

        return $attributes;
    }

    public function syntaxHighlighting(): bool
    {
        return $this->syntaxHighlighting;
    }
}
