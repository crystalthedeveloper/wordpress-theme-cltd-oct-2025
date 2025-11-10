const { __ } = wp.i18n;
const { registerBlockType } = wp.blocks;
const blockEditor = wp.blockEditor || wp.editor;
const {
    RichText,
    BlockControls,
    AlignmentToolbar,
    InspectorControls,
    URLInputButton,
    useBlockProps,
    PanelColorSettings,
} = blockEditor;
const { PanelBody, ToggleControl, TextControl, RangeControl } = wp.components;
const { createElement: el, Fragment } = wp.element;

const buildRelAttribute = (rel = '', openInNewTab = false) => {
    let relValues = rel ? rel.split(/\s+/).filter(Boolean) : [];

    if (openInNewTab) {
        if (!relValues.includes('noopener')) {
            relValues.push('noopener');
        }
        if (!relValues.includes('noreferrer')) {
            relValues.push('noreferrer');
        }
    } else {
        relValues = relValues.filter((value) => value !== 'noopener' && value !== 'noreferrer');
    }

    return relValues.join(' ') || undefined;
};

registerBlockType('cltd/button', {
    edit({ attributes, setAttributes, isSelected }) {
        const { text, url, align, rel, linkTarget, ariaLabel, borderRadius, borderWidth, borderColor } = attributes;
        const openInNewTab = linkTarget === '_blank';

        const blockProps = useBlockProps({
            className: align ? 'has-text-align-' + align : undefined,
            style: align ? { textAlign: align } : undefined,
        });

        const radiusStyle =
            typeof borderRadius === 'number'
                ? {
                      '--wp--custom--button--border--radius': borderRadius + 'px',
                      '--cltd-button-border-radius': borderRadius + 'px',
                  }
                : undefined;
        const widthStyle =
            typeof borderWidth === 'number'
                ? { '--cltd-button-border-width': borderWidth + 'px' }
                : undefined;
        const colorStyle = borderColor ? { '--cltd-button-border-color': borderColor } : undefined;

        const buttonStyle = Object.assign({}, radiusStyle || {}, widthStyle || {}, colorStyle || {});

        const interactiveProps = {
            className: 'wp-block-cltd-button__link',
            style: buttonStyle,
        };

        if (ariaLabel) {
            interactiveProps['aria-label'] = ariaLabel;
        }

        return el(
            Fragment,
            null,
            el(
                BlockControls,
                null,
                el(AlignmentToolbar, {
                    value: align,
                    onChange(nextAlign) {
                        setAttributes({ align: nextAlign });
                    },
                })
            ),
            el(
                InspectorControls,
                null,
                el(
                    PanelBody,
                    { title: __('Link settings', 'cltd-theme-oct-2025'), initialOpen: true },
                    el(TextControl, {
                        label: __('Button text', 'cltd-theme-oct-2025'),
                        value: text,
                        onChange(nextValue) {
                            setAttributes({ text: nextValue || '' });
                        },
                    }),
                    el(TextControl, {
                        label: __('Link URL', 'cltd-theme-oct-2025'),
                        type: 'url',
                        placeholder: 'https://example.com',
                        value: url || '',
                        onChange(nextValue) {
                            setAttributes({ url: nextValue || undefined });
                        },
                    }),
                    el(ToggleControl, {
                        label: __('Open in new tab', 'cltd-theme-oct-2025'),
                        checked: openInNewTab,
                        onChange(value) {
                            setAttributes({
                                linkTarget: value ? '_blank' : undefined,
                                rel: buildRelAttribute(rel, value),
                            });
                        },
                    }),
                    el(TextControl, {
                        label: __('Rel attribute', 'cltd-theme-oct-2025'),
                        help: __('Space separated values such as noopener, nofollow.', 'cltd-theme-oct-2025'),
                        value: rel || '',
                        onChange(nextValue) {
                            setAttributes({ rel: nextValue || undefined });
                        },
                    }),
                    el(TextControl, {
                        label: __('ARIA label', 'cltd-theme-oct-2025'),
                        value: ariaLabel || '',
                        onChange(nextValue) {
                            setAttributes({ ariaLabel: nextValue || undefined });
                        },
                    }),
                    el(RangeControl, {
                        label: __('Border radius', 'cltd-theme-oct-2025'),
                        value: typeof borderRadius === 'number' ? borderRadius : '',
                        onChange(nextValue) {
                            if (typeof nextValue === 'undefined') {
                                setAttributes({ borderRadius: null });
                                return;
                            }
                            setAttributes({ borderRadius: nextValue });
                        },
                        min: 0,
                        max: 60,
                        allowReset: true,
                        resetFallbackValue: null,
                    }),
                    el(RangeControl, {
                        label: __('Border width', 'cltd-theme-oct-2025'),
                        value: typeof borderWidth === 'number' ? borderWidth : '',
                        onChange(nextValue) {
                            if (typeof nextValue === 'undefined') {
                                setAttributes({ borderWidth: null });
                                return;
                            }
                            setAttributes({ borderWidth: nextValue });
                        },
                        min: 0,
                        max: 12,
                        allowReset: true,
                        resetFallbackValue: null,
                    })
                ),
                el(PanelColorSettings, {
                    title: __('Border color', 'cltd-theme-oct-2025'),
                    colorSettings: [
                        {
                            label: __('Border color', 'cltd-theme-oct-2025'),
                            value: borderColor || '',
                            onChange(nextColor) {
                                setAttributes({ borderColor: nextColor || '' });
                            },
                        },
                    ],
                })
            ),
            el(
                'div',
                blockProps,
                el(
                    url ? 'a' : 'button',
                    {
                        ...interactiveProps,
                        href: url || undefined,
                        target: url && linkTarget ? linkTarget : undefined,
                        rel: url && rel ? rel : undefined,
                        type: url ? undefined : 'button',
                        role: url ? 'button' : undefined,
                    },
                    el(RichText, {
                        tagName: 'span',
                        className: 'wp-block-cltd-button__label',
                        allowedFormats: ['core/bold', 'core/italic', 'core/strikethrough', 'core/underline'],
                        value: text,
                        placeholder: __('Add button text…', 'cltd-theme-oct-2025'),
                        onChange(nextValue) {
                            setAttributes({ text: nextValue });
                        },
                        withoutInteractiveFormatting: true,
                    })
                ),
                isSelected &&
                    el(URLInputButton, {
                        url,
                        onChange(nextUrl, post) {
                            setAttributes({
                                url: nextUrl,
                                ariaLabel: ariaLabel || (post ? post.title : ariaLabel),
                            });
                        },
                    })
            )
        );
    },
    save() {
        return null;
    },
});
