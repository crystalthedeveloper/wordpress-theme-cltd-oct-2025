/* global wp */
(function() {
    const { __, sprintf } = wp.i18n;
    const { registerBlockType, createBlock } = wp.blocks;
    const {
        InspectorControls,
        InnerBlocks,
        useBlockProps,
        useInnerBlocksProps,
    } = wp.blockEditor || wp.editor;
    const { PanelBody, RangeControl, SelectControl } = wp.components;
    const { Fragment, useEffect, useState, createElement: el } = wp.element;
    const { useSelect, useDispatch } = wp.data;

    const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

    const computeSpan = (value, totalColumns) => {
        if (!value) {
            return 1;
        }
        const ratio = value / 100;
        return Math.min(totalColumns, Math.max(1, Math.round(ratio * totalColumns)));
    };

    const getStackStyles = (breakpoint) => {
        const target = Math.max(320, parseInt(breakpoint, 10) || 768);
        return `
@media (max-width: ${target}px) {
    .cltd-columns[data-stack="${target}"] {
        grid-template-columns: 1fr !important;
    }

    .cltd-columns[data-stack="${target}"] > .cltd-column {
        grid-column: 1 / -1 !important;
    }

    .cltd-columns[data-stack="${target}"] .cltd-column video,
    .cltd-columns[data-stack="${target}"] .cltd-column .wp-block-video video {
        height: 300px;
    }
}
`;
    };

    const getDefaultWidths = (count) =>
        Array.from({ length: count }).map(() => 100 / count);

    const normalizeWidths = (values, count) => {
        if (!values || !values.length) {
            return getDefaultWidths(count);
        }
        const total = values.reduce((sum, current) => sum + (current || 0), 0);
        if (total <= 0) {
            return getDefaultWidths(count);
        }
        return values.map((value) => (value / total) * 100);
    };

    const arraysEqual = (a, b) => {
        if (!Array.isArray(a) || !Array.isArray(b)) {
            return false;
        }
        if (a.length !== b.length) {
            return false;
        }
        return a.every((value, index) => Math.abs(value - b[index]) < 0.01);
    };

    registerBlockType('cltd/columns', {
        title: __('CLTD Columns', 'cltd-theme-oct-2025'),
        description: __('Flexible columns layout for CLTD sites.', 'cltd-theme-oct-2025'),
        category: 'cltd',
        icon: 'columns',
        supports: {
            html: false,
        },
        attributes: {
            columns: {
                type: 'number',
                default: 2,
            },
            gap: {
                type: 'number',
                default: 1.5,
            },
            stackPoint: {
                type: 'number',
                default: 768,
            },
            direction: {
                type: 'string',
                default: 'horizontal',
            },
            columnWidths: {
                type: 'array',
                default: [],
            },
        },
        edit: (props) => {
            const { attributes, setAttributes, clientId } = props;
            const { columns, gap, stackPoint, direction, columnWidths } = attributes;
            const normalizedDirection =
                direction === 'row'
                    ? 'horizontal'
                    : direction === 'column'
                    ? 'vertical'
                    : direction;
            const stackBreakpoint = Math.max(320, parseInt(stackPoint, 10) || 768);
            const stackStyles = getStackStyles(stackBreakpoint);
            const [isStacked, setIsStacked] = useState(normalizedDirection === 'vertical');
            const { replaceInnerBlocks, updateBlockAttributes } = useDispatch('core/block-editor');
            const innerBlocks = useSelect(
                (select) => select('core/block-editor').getBlocks(clientId),
                [clientId]
            );

            useEffect(() => {
                if (normalizedDirection === 'vertical') {
                    setIsStacked(true);
                    return undefined;
                }

                if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
                    setIsStacked(false);
                    return undefined;
                }

                const mediaQuery = window.matchMedia(`(max-width: ${stackBreakpoint}px)`);

                const handleChange = (event) => {
                    setIsStacked(event.matches);
                };

                handleChange(mediaQuery);

                if (typeof mediaQuery.addEventListener === 'function') {
                    mediaQuery.addEventListener('change', handleChange);
                } else {
                    mediaQuery.addListener(handleChange);
                }

                return () => {
                    if (typeof mediaQuery.removeEventListener === 'function') {
                        mediaQuery.removeEventListener('change', handleChange);
                    } else {
                        mediaQuery.removeListener(handleChange);
                    }
                };
            }, [stackBreakpoint, normalizedDirection]);

            useEffect(() => {
                if (!innerBlocks) {
                    return;
                }

                if (innerBlocks.length < columns) {
                    const difference = columns - innerBlocks.length;
                    const newBlocks = [
                        ...innerBlocks,
                        ...Array.from({ length: difference }).map(() =>
                            createBlock('cltd/column', {
                                flexBasis: 100 / columns,
                                columnSpan: 1,
                            })
                        ),
                    ];
                    replaceInnerBlocks(clientId, newBlocks, false);
                } else if (innerBlocks.length > columns) {
                    replaceInnerBlocks(clientId, innerBlocks.slice(0, columns), false);
                }

                innerBlocks.slice(0, columns).forEach((block) => {
                    const basis = block.attributes.flexBasis || 100 / columns;
                    const span =
                        normalizedDirection === 'horizontal'
                            ? computeSpan(basis, columns)
                            : 1;
                    updateBlockAttributes(block.clientId, {
                        flexBasis: basis,
                        columnSpan: span,
                    });
                });
                const rawWidths = innerBlocks
                    .slice(0, columns)
                    .map((block) => block.attributes.flexBasis || 100 / columns);
                const normalized = normalizeWidths(rawWidths, columns);
                if (!arraysEqual(normalized, columnWidths)) {
                    setAttributes({ columnWidths: normalized });
                }
            }, [columns, innerBlocks, clientId, replaceInnerBlocks, normalizedDirection, columnWidths, setAttributes]);

            const currentRawWidths = innerBlocks && innerBlocks.length
                ? innerBlocks
                      .slice(0, columns)
                      .map((block) => block.attributes.flexBasis || 100 / columns)
                : getDefaultWidths(columns);
            const effectiveWidths =
                normalizedDirection === 'vertical'
                    ? []
                    : columnWidths.length === columns
                    ? columnWidths
                    : normalizeWidths(currentRawWidths, columns);
            const template =
                normalizedDirection === 'vertical'
                    ? '1fr'
                    : effectiveWidths.map((width) => `minmax(0, ${width}%)`).join(' ');
            const appliedTemplate = isStacked ? '1fr' : template;

            const wrapperClasses = [
                'cltd-columns',
                normalizedDirection === 'vertical' ? 'is-vertical' : 'is-horizontal',
            ];

            if (isStacked) {
                wrapperClasses.push('is-stacked');
            }

            const innerBlocksProps = useInnerBlocksProps(
                useBlockProps({
                    className: wrapperClasses.join(' '),
                    style: {
                        '--cltd-columns-gap': `${gap}rem`,
                        '--cltd-columns-stack': `${stackBreakpoint}px`,
                        '--cltd-columns-count': columns,
                        '--cltd-columns-template': appliedTemplate,
                        display: 'grid',
                        gridTemplateColumns: appliedTemplate,
                        gap: `${gap}rem`,
                        width: '100%',
                    },
                    'data-stack': stackBreakpoint,
                }),
                {
                    allowedBlocks: ['cltd/column'],
                }
            );

            const handleWidthChange = (index, value) => {
                const block = innerBlocks[index];
                if (!block) {
                    return;
                }

                const clampedValue = clamp(value, 10, 100);
                const span =
                    normalizedDirection === 'horizontal'
                        ? computeSpan(clampedValue, columns)
                        : 1;
                updateBlockAttributes(block.clientId, {
                    flexBasis: clampedValue,
                    columnSpan: span,
                });

                const rawWidths = innerBlocks
                    .slice(0, columns)
                    .map((child) => child.attributes.flexBasis || 100 / columns);
                const updatedRawWidths = rawWidths.map((width, idx) =>
                    idx === index ? clampedValue : width
                );
                const normalized = normalizeWidths(updatedRawWidths, columns);
                setAttributes({ columnWidths: normalized });
            };

            return el(
                Fragment,
                null,
                el('style', { key: 'cltd-stack-styles' }, stackStyles),
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('Layout', 'cltd-theme-oct-2025'), initialOpen: true },
                        el(RangeControl, {
                            label: __('Columns', 'cltd-theme-oct-2025'),
                            min: 1,
                            max: 4,
                            value: columns,
                            onChange: (value) => setAttributes({ columns: value }),
                        }),
                        el(RangeControl, {
                            label: __('Gap (rem)', 'cltd-theme-oct-2025'),
                            min: 0,
                            max: 4,
                            step: 0.1,
                            value: gap,
                            onChange: (value) => setAttributes({ gap: value }),
                        }),
                        el(RangeControl, {
                            label: __('Stack below (px)', 'cltd-theme-oct-2025'),
                            min: 360,
                            max: 1200,
                            step: 20,
                            value: stackPoint,
                            onChange: (value) => setAttributes({ stackPoint: value }),
                        }),
                        el(SelectControl, {
                            label: __('Layout direction', 'cltd-theme-oct-2025'),
                            value: direction,
                            options: [
                                { label: __('Horizontal', 'cltd-theme-oct-2025'), value: 'horizontal' },
                                { label: __('Vertical', 'cltd-theme-oct-2025'), value: 'vertical' },
                            ],
                            onChange: (value) => setAttributes({ direction: value }),
                        })
                    ),
                    el(
                        PanelBody,
                        { title: __('Column Widths', 'cltd-theme-oct-2025') },
                        innerBlocks.map((childBlock, index) =>
                            el(RangeControl, {
                                key: childBlock.clientId,
                                label: sprintf(
                                    __('Column %d width (%%)', 'cltd-theme-oct-2025'),
                                    index + 1
                                ),
                                min: 10,
                                max: 100,
                                value: childBlock.attributes.flexBasis || 0,
                                onChange: (value) => handleWidthChange(index, value),
                            })
                        )
                    )
                ),
                el('div', innerBlocksProps)
            );
        },
        save: (props) => {
            const { attributes } = props;
            const { columns, gap, stackPoint, direction, columnWidths } = attributes;
            const columnCount = Math.max(
                1,
                Math.min(4, Number(columns) || parseInt(columns, 10) || 1)
            );

            const normalizedDirection =
                direction === 'row'
                    ? 'horizontal'
                    : direction === 'column'
                    ? 'vertical'
                    : direction;

            const effectiveWidths =
                normalizedDirection === 'horizontal'
                    ? Array.isArray(columnWidths) && columnWidths.length === columnCount
                        ? columnWidths
                        : getDefaultWidths(columnCount)
                    : [];
            const template =
                normalizedDirection === 'vertical'
                    ? '1fr'
                    : effectiveWidths.map((width) => `minmax(0, ${width}%)`).join(' ');

            const directionClass =
                normalizedDirection === 'vertical' ? 'is-vertical' : 'is-horizontal';
            const stackedClass = normalizedDirection === 'vertical' ? ' is-stacked' : '';
            const stackBreakpoint = Math.max(320, parseInt(stackPoint, 10) || 768);
            const stackStyles = getStackStyles(stackBreakpoint);

            return el(Fragment, null,
                el('style', null, stackStyles),
                el('div', useBlockProps.save({
                    className: `cltd-columns ${directionClass}${stackedClass}`,
                    style: {
                        '--cltd-columns-gap': `${gap}rem`,
                        '--cltd-columns-stack': `${stackBreakpoint}px`,
                        '--cltd-columns-count': columnCount,
                        '--cltd-columns-template': template,
                        display: 'grid',
                        gridTemplateColumns: template,
                        gap: `${gap}rem`,
                        width: '100%',
                    },
                    'data-stack': stackBreakpoint,
                }), el(InnerBlocks.Content))
            );
        },
    });

    registerBlockType('cltd/column', {
        title: __('CLTD Column', 'cltd-theme-oct-2025'),
        parent: ['cltd/columns'],
        icon: 'align-left',
        supports: {
            reusable: false,
            html: false,
        },
        attributes: {
            flexBasis: {
                type: 'number',
                default: 0,
            },
            columnSpan: {
                type: 'number',
                default: 1,
            },
        },
        edit: (props) => {
            const { attributes } = props;
            const { flexBasis, columnSpan } = attributes;
            const flexValue = flexBasis ? `${flexBasis}%` : null;
            const spanValue = columnSpan || 1;
            const blockProps = useBlockProps({
                className: 'cltd-column',
                style: {
                    '--cltd-column-width': flexValue || 'auto',
                    '--cltd-column-span': spanValue,
                    gridColumn: `span ${spanValue}`,
                },
            });
            const innerProps = useInnerBlocksProps(blockProps, {
                templateLock: false,
            });

            return el('div', innerProps);
        },
        save: (props) => {
            const { attributes } = props;
            const { flexBasis, columnSpan } = attributes;
            const flexValue = flexBasis ? `${flexBasis}%` : null;
            const spanValue = columnSpan || 1;
            const blockProps = useBlockProps.save({
                className: 'cltd-column',
                style: {
                    '--cltd-column-width': flexValue || 'auto',
                    '--cltd-column-span': spanValue,
                    gridColumn: `span ${spanValue}`,
                },
            });

            return el(
                'div',
                blockProps,
                el(InnerBlocks.Content)
            );
        },
    });
})();
