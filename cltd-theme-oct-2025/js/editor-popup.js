( function( wp ) {
  const { registerPlugin } = wp.plugins;
  const { PluginDocumentSettingPanel } = wp.editPost || {};
  const { select, useSelect, useDispatch } = wp.data;
  const { CheckboxControl, PanelRow } = wp.components;
  const { createElement, useEffect } = wp.element;

  if ( ! registerPlugin || ! PluginDocumentSettingPanel ) {
    return;
  }

  function PopupPanel() {
    const postType = useSelect( () => select( 'core/editor' ).getCurrentPostType(), [] );
    const meta = useSelect( () => select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {}, [] );
    const { editPost } = useDispatch( 'core/editor' );

    const isEnabled = !!(meta && meta.cltd_open_in_popup);

    if ( postType !== 'page' ) {
      return null;
    }

    const onChange = ( value ) => {
      editPost( { meta: { ...meta, cltd_open_in_popup: value ? true : false } } );
    };

    return createElement( PluginDocumentSettingPanel, {
      name: 'cltd-popup-settings',
      title: 'Popup Display',
      className: 'cltd-popup-settings-panel'
    },
      createElement( PanelRow, null,
        createElement( CheckboxControl, {
          label: 'Open this page inside the popup',
          checked: isEnabled,
          onChange
        } )
      ),
      createElement(
        'p',
        { className: 'cltd-popup-settings-panel__description' },
        'When enabled, links to this page open inside the global popup instead of navigating away.'
      )
    );
  }

  registerPlugin( 'cltd-popup-settings', {
    render: PopupPanel,
    icon: 'excerpt-view'
  } );
} )( window.wp || {} );
