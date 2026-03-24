/**
 * Menu Block Script
 */

document.addEventListener('alpine:init', () => {
    Alpine.data('Menu', () => ({
        open: false,
        query: '',
        results: [],
        loading: false,
        _timer: null,

        init() {
            this.$watch('open', (val) => {
                if (val) {
                    this.$nextTick(() => this.$refs.searchInput?.focus());
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            });

            this.$watch('query', (val) => {
                clearTimeout(this._timer);
                if (val.trim().length < 2) {
                    this.results = [];
                    return;
                }
                this._timer = setTimeout(() => this.search(val), 350);
            });

            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.open) this.closeSearch();
            });
        },

        closeSearch() {
            this.open = false;
            this.query = '';
            this.results = [];
        },

        async search(query) {
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    search: query,
                    type: 'post',
                    subtype: 'post,page',
                    per_page: 8,
                });
                const res = await fetch(`/wp-json/wp/v2/search?${params}`);

                if (!res.ok) throw new Error(res.statusText);
                this.results = await res.json();
            } catch {
                this.results = [];
            } finally {
                this.loading = false;
            }
        },
    }));
});
