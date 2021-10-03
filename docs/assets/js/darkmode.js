const globalDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
const localMode = localStorage.getItem('theme');

if (globalDark && (localMode === null)) {

  localStorage.setItem('theme', 'light');
  document.documentElement.classList.add('data-light-mode');

}

if (globalDark && (localMode === 'dark')) {

  document.documentElement.classList.add('data-dark-mode');

}

if (globalDark && (localMode === 'sepia')) {

  document.documentElement.classList.add('data-sepia-mode');

}

if (localMode === 'dark') {

  document.documentElement.classList.add('data-dark-mode');

}

if (localMode === 'sepia') {

  document.documentElement.classList.add('data-sepia-mode');

}


const mode = document.getElementById('mode');
const body = document.querySelector('body')

if (mode !== null) {

  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {

    if (event.matches) {

      localStorage.setItem('theme', 'dark');
      body.classList.add('data-dark-mode');

    } else {

      localStorage.setItem('theme', 'light');
      body.classList.add('data-light-mode');

    }

  })

  window.matchMedia('(prefers-color-scheme: sepia)').addEventListener('change', event => {

    if (event.matches) {

      localStorage.setItem('theme', 'sepia');
      body.classList.add('data-sepia-mode');

    } else {

      localStorage.setItem('theme', 'light');
      body.classList.add('data-light-mode');

    }

  })

  mode.addEventListener('click', () => {

    var isDark = body.classList.contains('data-dark-mode')
    var isSepia = body.classList.contains('data-sepia-mode')
    var isLight = body.classList.contains('data-light-mode')
    if(isDark) {
      localStorage.setItem('theme', 'sepia');
    }
    if(isSepia) {
      localStorage.setItem('theme', 'light');
    }
    if(isLight) {
      localStorage.setItem('theme', 'dark');
    }

    body.classList.remove('data-dark-mode');
    body.classList.remove('data-sepia-mode');
    body.classList.remove('data-light-mode');
    if (localStorage.getItem('theme') === 'dark') {
      body.classList.add('data-dark-mode');
    } else if(localStorage.getItem('theme') === 'sepia') {
      body.classList.add('data-sepia-mode');
    } else if(localStorage.getItem('theme') === 'light') {
      body.classList.add('data-light-mode');
    }
  });

  body.classList.remove('data-dark-mode');
  body.classList.remove('data-sepia-mode');
  body.classList.remove('data-light-mode');
  if (localStorage.getItem('theme') === 'dark') {
    body.classList.add('data-dark-mode');
  } else if(localStorage.getItem('theme') === 'sepia') {
    body.classList.add('data-sepia-mode');
  } else if(localStorage.getItem('theme') === 'light') {
    body.classList.add('data-light-mode');
  }

}
