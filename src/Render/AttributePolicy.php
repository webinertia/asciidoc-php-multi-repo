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

    /**
     * The `attributes` option for the converter.
     *
     * `false` unsets an attribute, which is the array form of the AsciiDoc `name!` syntax. The
     * highlighter is always unset: the converter's highlighter emits its own payload, which is a
     * CDN request the site cannot make, and it tokenises the source server side. The site ships a
     * vendored highlighter instead and leaves the `hljs` class the converter puts on a listing
     * block, which is what the vendored stylesheet paints.
     *
     * @return array<string, string|false>
     */
    public function attributes(): array
    {
        $attributes = [
            'stylesheet'         => false,
            'noembed'            => '',
            'linkcss'            => '',
            'toc'                => false,
            'docinfo'            => false,
            'source-highlighter' => false,
        ];

        foreach (self::CLOCK_ATTRIBUTES as $name) {
            $attributes[$name] = '';
        }

        return $attributes;
    }
}
