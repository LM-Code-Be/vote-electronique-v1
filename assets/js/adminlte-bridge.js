(() => {
  window.theme = window.theme || {
    primary: '#2563eb',
    secondary: '#64748b',
    success: '#10b981',
    info: '#0ea5e9',
    warning: '#f59e0b',
    danger: '#ef4444',
    white: '#ffffff',
    black: '#0f172a',
  };

  const body = document.body;
  const sidebar = document.querySelector('.js-sidebar');
  if (sidebar) {
    body.classList.add('with-sidebar');
  } else {
    body.classList.remove('with-sidebar');
  }

  const closeSidebar = () => body.classList.remove('sidebar-open');
  const toggleSidebar = () => body.classList.toggle('sidebar-open');

  document.addEventListener('click', (e) => {
    const toggle = e.target.closest('.js-sidebar-toggle');
    if (toggle) {
      e.preventDefault();
      toggleSidebar();
      return;
    }

    if (!sidebar || window.innerWidth > 991) {
      return;
    }

    const clickedSidebar = e.target.closest('.js-sidebar');
    if (!clickedSidebar && body.classList.contains('sidebar-open')) {
      closeSidebar();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeSidebar();
    }
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 991) {
      closeSidebar();
    }
  });
})();
