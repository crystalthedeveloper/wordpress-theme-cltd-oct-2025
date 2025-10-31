(function () {
  const { registerBlockType } = wp.blocks;
  const { InspectorControls } = wp.blockEditor || wp.editor;
  const { PanelBody, SelectControl, TextControl } = wp.components;
  const { Fragment, useMemo } = wp.element;
  const { useSelect } = wp.data;
  const ServerSideRender = wp.serverSideRender;
  const { __ } = wp.i18n;

  registerBlockType('cltd/popup-group', {
    edit: (props) => {
      const { attributes, setAttributes } = props;
      const groups = useSelect(
        (select) => {
          return select('core').getEntityRecords('postType', 'popup_group', {
            per_page: -1,
            orderby: 'menu_order',
            order: 'asc'
          });
        },
        []
      ) || [];

      const groupOptions = useMemo(() => {
        const options = [
          { label: __('All Groups', 'cltd-theme-oct-2025'), value: 0 }
        ];
        groups.forEach((group) => {
          options.push({ label: group.title.rendered, value: group.id });
        });
        return options;
      }, [groups]);

      const spanOptions = useMemo(() => ([
        { label: __('Inherit group setting', 'cltd-theme-oct-2025'), value: '' },
        { label: __('1 column', 'cltd-theme-oct-2025'), value: '1' },
        { label: __('2 columns', 'cltd-theme-oct-2025'), value: '2' },
        { label: __('3 columns', 'cltd-theme-oct-2025'), value: '3' }
      ]), []);

      const spanValue = attributes.columnSpan && attributes.columnSpan > 0 ? String(attributes.columnSpan) : '';
      const orderValue = (attributes.columnOrder === null || typeof attributes.columnOrder === 'undefined')
        ? ''
        : String(attributes.columnOrder);

      return (
        wp.element.createElement(
          Fragment,
          null,
          wp.element.createElement(
            InspectorControls,
            null,
            wp.element.createElement(
              PanelBody,
              { title: __('Popup Group Settings', 'cltd-theme-oct-2025'), initialOpen: true },
              wp.element.createElement(SelectControl, {
                label: __('Group', 'cltd-theme-oct-2025'),
                value: attributes.groupId || 0,
                options: groupOptions,
                onChange: (value) => setAttributes({ groupId: parseInt(value, 10) || 0 })
              }),
              wp.element.createElement(SelectControl, {
                label: __('Order', 'cltd-theme-oct-2025'),
                value: attributes.order || 'asc',
                options: [
                  { label: __('Ascending', 'cltd-theme-oct-2025'), value: 'asc' },
                  { label: __('Descending', 'cltd-theme-oct-2025'), value: 'desc' }
                ],
                onChange: (value) => setAttributes({ order: value })
              }),
              wp.element.createElement(SelectControl, {
                label: __('Column Span', 'cltd-theme-oct-2025'),
                value: spanValue,
                options: spanOptions,
                onChange: (value) => {
                  setAttributes({ columnSpan: value === '' ? 0 : parseInt(value, 10) });
                }
              }),
              wp.element.createElement(TextControl, {
                label: __('Grid Order Override', 'cltd-theme-oct-2025'),
                type: 'number',
                help: __('Lower numbers appear first. Leave blank to inherit.', 'cltd-theme-oct-2025'),
                value: orderValue,
                onChange: (value) => {
                  setAttributes({ columnOrder: value === '' ? null : parseInt(value, 10) });
                }
              })
            )
          ),
          wp.element.createElement(ServerSideRender, {
            block: 'cltd/popup-group',
            attributes
          })
        )
      );
    },
    save: () => null
  });
})();
