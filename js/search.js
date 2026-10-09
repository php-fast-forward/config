var Search = (function () {
    var fuse;
    var index = [];
    var options = {
        shouldSort: true,
        threshold: 0.6,
        location: 0,
        distance: 100,
        maxPatternLength: 32,
        minMatchCharLength: 1,
        keys: [
            "fqsen",
            "name",
            "summary",
            "url"
        ]
    };

    function debounce(func, wait, immediate) {
        var timeout;

        return function executedFunction() {
            var context = this;
            var args = arguments;

            var later = function () {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };

            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }

    function close() {
        var form = document.querySelector('[data-search-form]');
        var searchResults = document.querySelector('[data-search-results]');
        var searchField = document.querySelector('[data-search-form] input[type="search"]');
        var entries = document.querySelector('[data-search-results] .phpdocumentor-search-results__entries');

        document.body.classList.remove('phpdocumentor-search--active-body');

        form.classList.remove('phpdocumentor-search--has-results');
        searchResults.classList.add('phpdocumentor-search-results--hidden');
        searchResults.classList.remove('show');
        if (entries) {
            entries.innerHTML = '';
        }

        if (searchField) {
            searchField.blur();
        }
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value || '';
        return div.innerHTML;
    }

    function renderResult(result) {
        var summary = result.summary ? escapeHtml(result.summary) : '';
        var fqsen = result.fqsen ? escapeHtml(result.fqsen) : '';
        var name = escapeHtml(result.name || 'Untitled');
        var url = result.url;

        return '' +
            '<li class="list-group-item px-3 py-3">' +
                '<a href="' + url + '" class="text-decoration-none d-block">' +
                    '<div class="d-flex align-items-start justify-content-between gap-3">' +
                        '<div class="flex-grow-1 overflow-hidden">' +
                            '<div class="fw-semibold text-body mb-1">' + name + '</div>' +
                            (fqsen ? '<div class="small text-body-secondary text-truncate mb-2">' + fqsen + '</div>' : '') +
                            (summary ? '<div class="small text-body-secondary">' + summary + '</div>' : '') +
                        '</div>' +
                        '<span class="badge text-bg-light border flex-shrink-0">API</span>' +
                    '</div>' +
                '</a>' +
            '</li>';
    }

    function search(event) {
        event.stopPropagation();

        var form = document.querySelector('[data-search-form]');
        var searchResults = document.querySelector('[data-search-results]');
        var searchResultEntries = document.querySelector('[data-search-results] .phpdocumentor-search-results__entries');
        var value = event.target.value ? event.target.value.trim() : '';

        searchResultEntries.innerHTML = '';

        if (!value) {
            close();
            return;
        }

        document.body.classList.add('phpdocumentor-search--active-body');

        form.classList.add('phpdocumentor-search--has-results');
        searchResults.classList.remove('phpdocumentor-search-results--hidden');
        searchResults.classList.add('show');

        var results = fuse.search(value, { limit: 25 });

        if (!results.length) {
            searchResultEntries.innerHTML =
                '<li class="list-group-item px-3 py-4 text-center text-body-secondary">' +
                    '<i class="fas fa-search me-2"></i>No results found' +
                '</li>';
            return;
        }

        results.forEach(function (result) {
            searchResultEntries.innerHTML += renderResult(result);
        });
    }

    function appendIndex(added) {
        index = index.concat(added);

        if (typeof fuse !== 'undefined') {
            fuse = new Fuse(index, options);
        }
    }

    function init() {
        fuse = new Fuse(index, options);

        var form = document.querySelector('[data-search-form]');
        var searchField = document.querySelector('[data-search-form] input[type="search"]');
        var closeButton = document.querySelector('.phpdocumentor-search-results__close');
        var searchResults = document.querySelector('[data-search-results]');
        var clearButton = document.querySelector('[data-search-clear]');

        if (!form || !searchField || !searchResults) {
            return;
        }

        if (closeButton) {
            closeButton.addEventListener('click', function (event) {
                event.preventDefault();
                close();
            });
        }

        if (clearButton) {
            clearButton.addEventListener('click', function (event) {
                event.preventDefault();
                searchField.value = '';
                close();
            });
        }

        document.addEventListener('click', function (event) {
            if (!form.contains(event.target)) {
                close();
            }
        });

        searchResults.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        form.classList.remove('d-none');
        form.classList.add('d-block', 'opacity-100');

        searchField.setAttribute('placeholder', 'Search (Press "/" to focus)');
        searchField.removeAttribute('disabled');
        searchField.addEventListener('keyup', debounce(search, 250));
        searchField.addEventListener('focus', function () {
            if (searchField.value.trim()) {
                searchResults.classList.remove('phpdocumentor-search-results--hidden');
                searchResults.classList.add('show');
            }
        });

        window.addEventListener('keyup', function (event) {
            if (event.key === '/') {
                if (document.activeElement !== searchField) {
                    event.preventDefault();
                    searchField.focus();
                }
            }

            if (event.key === 'Escape') {
                close();
            }
        });
    }

    return {
        appendIndex,
        init
    }
})();

window.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-search-form]');

    if (form) {
        form.classList.remove('d-none');
        form.classList.add('d-block');
    }
});

window.addEventListener('load', function () {
    Search.init();
});
