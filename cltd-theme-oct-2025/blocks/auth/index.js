(function(blocks, element, serverSideRender, i18n) {
  if (!blocks || !element || !serverSideRender || !i18n) {
    return;
  }

  const { registerBlockType } = blocks;
  const { createElement: el, Fragment } = element;
  const { __ } = i18n;
  const ServerSideRender = serverSideRender;

  const forms = [
    {
      name: 'cltd/auth-login',
      title: __('CLTD Login Form', 'cltd-theme-oct-2025'),
      description: __('Displays the CLTD login form with helpful links.', 'cltd-theme-oct-2025'),
      icon: 'lock'
    },
    {
      name: 'cltd/auth-signup',
      title: __('CLTD Sign Up Form', 'cltd-theme-oct-2025'),
      description: __('Collects information and creates an account.', 'cltd-theme-oct-2025'),
      icon: 'admin-users'
    },
    {
      name: 'cltd/auth-forgot',
      title: __('CLTD Forgot Password Form', 'cltd-theme-oct-2025'),
      description: __('Sends a password reset link to the user.', 'cltd-theme-oct-2025'),
      icon: 'email'
    },
    {
      name: 'cltd/auth-account',
      title: __('CLTD My Account', 'cltd-theme-oct-2025'),
      description: __('Shows profile details and Clown Hunt stats for logged-in users.', 'cltd-theme-oct-2025'),
      icon: 'id'
    }
  ];

  forms.forEach((form) => {
    registerBlockType(form.name, {
      title: form.title,
      description: form.description,
      category: 'cltd',
      icon: form.icon,
      supports: {
        html: false
      },
      edit: () =>
        el(
          Fragment,
          null,
          el(ServerSideRender, {
            block: form.name
          })
        ),
      save: () => null
    });
  });
})(window.wp.blocks, window.wp.element, window.wp.serverSideRender, window.wp.i18n);
