const globalDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
const localMode = localStorage.getItem('theme');

if (globalDark && (localMode === null)) {

  localStorage.setItem('theme', 'light');
  document.documentElement.setAttribute('data-light-mode', '');

}

if (globalDark && (localMode === 'dark')) {

  document.documentElement.setAttribute('data-dark-mode', '');

}

if (globalDark && (localMode === 'sepia')) {

  document.documentElement.setAttribute('data-sepia-mode', '');

}

if (localMode === 'dark') {

  document.documentElement.setAttribute('data-dark-mode', '');

}

if (localMode === 'sepia') {

  document.documentElement.setAttribute('data-sepia-mode', '');

}
