(function () {
  const { registerPlugin } = wp.plugins;
  const { PluginDocumentSettingPanel } = wp.editPost;
  const { TextControl, TextareaControl, Button, Notice, PanelBody, CheckboxControl } = wp.components;
  const { MediaUpload, MediaUploadCheck } = wp.blockEditor || wp.editor;
  const { useSelect, useDispatch } = wp.data;
  const { useState, Fragment, createElement: el } = wp.element;
  const { __ } = wp.i18n;

  const META_KEYS = CLTDSEO.metaKeys;

  const useMeta = () => {
    const meta = useSelect((select) => select('core/editor').getEditedPostAttribute('meta') || {}, []);
    const title = useSelect((select) => select('core/editor').getEditedPostAttribute('title') || '', []);
    const { editPost } = useDispatch('core/editor');

    const updateMeta = (key, value) => {
      editPost({ meta: { ...meta, [key]: value } });
    };

    return { meta, updateMeta, postTitle: title };
  };

  const DescriptionGenerator = ({ onGenerated }) => {
    const [status, setStatus] = useState('');
    const [isWorking, setIsWorking] = useState(false);
    const postId = useSelect((select) => select('core/editor').getCurrentPostId(), []);

    const generate = () => {
      if (!postId) {
        return;
      }

      setIsWorking(true);
      setStatus(CLTDSEO.messages.generating);

      window
        .fetch(CLTDSEO.ajaxUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: new URLSearchParams({
            action: 'cltd_seo_generate_description',
            nonce: CLTDSEO.nonce,
            postId
          }).toString()
        })
        .then((response) => response.json())
        .then((payload) => {
          if (payload.success && payload.data) {
            onGenerated(payload.data);
            setStatus('');
          } else {
            setStatus(CLTDSEO.messages.error);
          }
        })
        .catch(() => {
          setStatus(CLTDSEO.messages.error);
        })
        .finally(() => {
          setIsWorking(false);
        });
    };

    return el(
      'div',
      { className: 'cltd-seo-generator' },
      el(
        Button,
        { isSecondary: true, onClick: generate, disabled: isWorking },
        isWorking ? __('Generating…', 'cltd-seo') : __('Generate with AI', 'cltd-seo')
      ),
      status &&
        el(Notice, { status: 'info', isDismissible: false }, status)
    );
  };

  const PreviewCard = ({ title, description, image }) =>
    el(
      'div',
      { className: 'cltd-seo-preview' },
      el(
        'div',
        { className: 'cltd-seo-preview__image' },
          image
            ? el('img', { src: image, alt: '' })
            : el('div', { className: 'cltd-seo-preview__placeholder' }, 'OG')
      ),
      el(
        'div',
        { className: 'cltd-seo-preview__content' },
        el('strong', { className: 'cltd-seo-preview__title' }, title || __('Untitled Page', 'cltd-seo')),
        el('p', { className: 'cltd-seo-preview__description' }, description || __('Meta description preview will appear here.', 'cltd-seo'))
      )
    );

  const MetaPanel = () => {
    const { meta, updateMeta, postTitle } = useMeta();
    const currentMeta = {
      title: meta[META_KEYS.title] || '',
      description: meta[META_KEYS.description] || '',
      ogTitle: meta[META_KEYS.ogTitle] || '',
      ogDescription: meta[META_KEYS.ogDescription] || '',
      image: meta[META_KEYS.image] || '',
      ogTitleSame: !!meta[META_KEYS.ogTitleSame],
      ogDescriptionSame: !!meta[META_KEYS.ogDescriptionSame],
      ogImageSame: !!meta[META_KEYS.ogImageSame]
    };

    const handleGenerated = (text) => {
      updateMeta(META_KEYS.description, text);
    };

    return el(
      PluginDocumentSettingPanel,
      {
        name: 'cltd-seo-panel',
        title: __('CLTD SEO', 'cltd-seo'),
        className: 'cltd-seo-panel'
      },
      el(
        PanelBody,
        null,
        el(TextControl, {
          label: __('Meta Title', 'cltd-seo'),
          value: currentMeta.title,
          maxLength: 120,
          onChange: (value) => updateMeta(META_KEYS.title, value),
          help: __('Recommended 50–60 characters.', 'cltd-seo')
        }),
        el(TextareaControl, {
          label: __('Meta Description', 'cltd-seo'),
          value: currentMeta.description,
          maxLength: 320,
          onChange: (value) => updateMeta(META_KEYS.description, value),
          help: __('Recommended 140–160 characters.', 'cltd-seo')
        }),
        el(DescriptionGenerator, { onGenerated: handleGenerated })
      ),
      el(
        PanelBody,
        { title: __('Open Graph', 'cltd-seo'), initialOpen: false },
        el(CheckboxControl, {
          label: __('Same as SEO Title Tag', 'cltd-seo'),
          checked: currentMeta.ogTitleSame,
          onChange: (checked) => updateMeta(META_KEYS.ogTitleSame, checked)
        }),
        el(TextControl, {
          label: __('OG Title', 'cltd-seo'),
          value: currentMeta.ogTitle,
          disabled: currentMeta.ogTitleSame,
          onChange: (value) => updateMeta(META_KEYS.ogTitle, value)
        }),
        el(CheckboxControl, {
          label: __('Same as SEO Meta Description', 'cltd-seo'),
          checked: currentMeta.ogDescriptionSame,
          onChange: (checked) => updateMeta(META_KEYS.ogDescriptionSame, checked)
        }),
        el(TextareaControl, {
          label: __('OG Description', 'cltd-seo'),
          value: currentMeta.ogDescription,
          disabled: currentMeta.ogDescriptionSame,
          onChange: (value) => updateMeta(META_KEYS.ogDescription, value)
        }),
        el(CheckboxControl, {
          label: __('Same as SEO Image', 'cltd-seo'),
          checked: currentMeta.ogImageSame,
          onChange: (checked) => updateMeta(META_KEYS.ogImageSame, checked)
        }),
        !currentMeta.ogImageSame &&
          el(
            MediaUploadCheck,
            null,
            el(MediaUpload, {
              onSelect: (media) => updateMeta(META_KEYS.image, media && media.url ? media.url : ''),
              value: currentMeta.image,
              allowedTypes: ['image'],
              render: ({ open }) =>
                el(
                  Fragment,
                  null,
                  el(
                    Button,
                    { onClick: open, isSecondary: true },
                    currentMeta.image ? __('Change Image', 'cltd-seo') : __('Select Image', 'cltd-seo')
                  ),
                  currentMeta.image &&
                    el(
                      Button,
                      {
                        isLink: true,
                        isDestructive: true,
                        onClick: () => updateMeta(META_KEYS.image, ''),
                        className: 'cltd-seo-reset-image'
                      },
                      __('Remove', 'cltd-seo')
                    )
                )
            })
          ),
        currentMeta.image && !currentMeta.ogImageSame && el('img', { src: currentMeta.image, alt: '', className: 'cltd-seo-og-preview' })
      ),
      el(
        PanelBody,
        { title: __('Social Preview', 'cltd-seo'), initialOpen: true },
        el(PreviewCard, {
          title: currentMeta.title || postTitle,
          description: currentMeta.description,
          image: currentMeta.image
        })
      )
    );
  };

  registerPlugin('cltd-seo-panel', { render: MetaPanel });
})();
