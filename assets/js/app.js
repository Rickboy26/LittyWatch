(() => {
  const sidebar = document.querySelector('.sidebar');
  const toggle = document.querySelector('[data-menu-toggle]');
  toggle?.addEventListener('click', () => sidebar?.classList.toggle('open'));
  document.addEventListener('click', (event) => {
    if (window.innerWidth > 760 || !sidebar?.classList.contains('open')) return;
    if (!sidebar.contains(event.target) && event.target !== toggle) sidebar.classList.remove('open');
  });
})();
