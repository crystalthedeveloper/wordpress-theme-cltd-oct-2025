(() => {
  const { addFilter } = wp.hooks;
  const { createHigherOrderComponent } = wp.compose;
  const { Fragment } = wp.element;
  const { InspectorControls } = wp.blockEditor || wp.editor;
  const { PanelBody, CheckboxControl } = wp.components;
  const { __ } = wp.i18n;

  const supportedBlocks = wp.hooks.applyFilters('cltdVisibility.supportedBlocks', [
    'core/heading',
    'core/paragraph'
  ]);

  const blockSupportsVisibility = (name) => {
    if (!supportedBlocks || !Array.isArray(supportedBlocks) || !supportedBlocks.length) {
      return true;
    }
    return supportedBlocks.includes(name);
  };

  addFilter(
    'blocks.registerBlockType',
    'cltd/visibility-attribute',
    (settings, name) => {
      if (!blockSupportsVisibility(name)) {
        return settings;
      }

      settings.attributes = Object.assign({}, settings.attributes, {
        cltdShowWhenLoggedIn: {
          type: 'boolean',
          default: false
        }
      });

      return settings;
    }
  );

  const withCltdVisibilityControls = createHigherOrderComponent(
    (BlockEdit) => (props) => {
      if (!blockSupportsVisibility(props.name)) {
        return wp.element.createElement(BlockEdit, props);
      }

      const { attributes: { cltdShowWhenLoggedIn = false }, setAttributes } = props;

      const onChange = (value) => {
        setAttributes({ cltdShowWhenLoggedIn: !!value });
      };

      return wp.element.createElement(
        Fragment,
        null,
        wp.element.createElement(BlockEdit, props),
        wp.element.createElement(
          InspectorControls,
          null,
          wp.element.createElement(
            PanelBody,
            {
              title: __('CLTD Visibility', 'cltd-theme-oct-2025'),
              initialOpen: false
            },
            wp.element.createElement(CheckboxControl, {
              label: __('Show this block only to logged in users', 'cltd-theme-oct-2025'),
              checked: !!cltdShowWhenLoggedIn,
              onChange
            })
          )
        )
      );
    },
    'withCltdVisibilityControls'
  );

  addFilter('editor.BlockEdit', 'cltd/visibility-controls', withCltdVisibilityControls);

  const addVisibilityProp = (saveElementProps, blockType, attributes) => {
    if (!blockSupportsVisibility(blockType.name)) {
      return saveElementProps;
    }

    if (attributes.cltdShowWhenLoggedIn) {
      saveElementProps = saveElementProps || {};
      saveElementProps['data-cltd-visibility'] = 'logged-in';
    }

    return saveElementProps;
  };

  addFilter(
    'blocks.getSaveContent.extraProps',
    'cltd/visibility-extra-props',
    addVisibilityProp
  );
})();
