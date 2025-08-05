<script src="https://cdn.jsdelivr.net/npm/vue@2.5.13/dist/vue.js"></script>

<?php

/**
 * Loads countries into a small Vue instance.
 */
function loadCountries($countries) { ?>
    <script type="text/javascript">
        new Vue({
            el: '#app',
            created: function () { this.load(); },
            data: { countries: [] },
            methods: {
                load: function () {
                    this.countries = <?= $countries; ?>;
                }
            }
        });
    </script>
<?php }

/**
 * Simple binding for live character count.
 */
function letterCount() { ?>
    <script type="text/javascript">
        new Vue({
            el: '#text',
            data: { quote: '' }
        });
    </script>
<?php }

/**
 * Publications table controller (list, search, paging, likes, comments).
 * @param  string $items  JSON array of items
 * @param  string $votes  JSON array of voted publication IDs
 */
function loadTable($items, $votes) { ?>
    <script type="text/javascript">
        new Vue({
            el: '#table',
            created: function () { this.load(); },
            data: {
                items: [],        // items array
                votes: [],        // voted publication IDs
                quote: '',        // bound to search input
                page: 1,          // current page
                size: 25          // items per page
            },
            methods: {
                load: function () {
                    this.items = <?= $items ?>;
                    this.votes = <?= $votes ?>;
                },
                contains: function (key) {
                    for (var i = 0; i < this.votes.length; i++) {
                        if (String(this.votes[i]) === String(key)) return true;
                    }
                    return false;
                },
                next: function () {
                    if (this.page < this.count) this.page++;
                },
                prev: function () {
                    if (this.page > 1) this.page--;
                },

                sendComment: function (quoteId) {
                    var input = document.getElementById('comment-text-' + quoteId);
                    if (!input) return;
                    var body = (input.value || '').trim();
                    if (!body) return;

                    var vm = this;
                    var form = new URLSearchParams();
                    form.append('quote_id', quoteId);
                    form.append('body', body);

                    fetch('/index.php?page=comment_add', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': (window.csrfToken || '')},
                        body: form.toString()
                    })
                        .then(function(r){ return r.text(); })
                        .then(function(t){
                            try {
                                var res = JSON.parse(t);
                                if (res && res.ok === true) {
                                    // update UI without reload
                                    var item = vm.items.find(function(x){ return String(x.id) === String(quoteId); });
                                    if (item) {
                                        if (!Array.isArray(item.comments)) item.comments = [];
                                        item.comments.push({
                                            id: 0, body: body,
                                            created: new Date().toISOString().slice(0,19).replace('T',' '),
                                            guid: (window.currentUserGuid || ''), user: (window.currentUsername || 'You')
                                        });
                                    }
                                    input.value = '';
                                } else {
                                    alert((res && res.msg) ? res.msg : 'Failed to add comment.');
                                }
                            } catch(e) {
                                // Not JSON — show the server output to diagnose
                                alert(t.slice(0, 500) || 'Empty response');
                            }
                        })
                        .catch(function(){ alert('Network error'); });
                }

            },
            computed: {
                search: function () {
                    var q = (this.quote || '').toLowerCase();
                    if (!q) return this.items;
                    return this.items.filter(function (item) {
                        var text = (item.quote || '').toLowerCase();
                        var user = (item.user  || '').toLowerCase();
                        return text.indexOf(q) !== -1 || user.indexOf(q) !== -1;
                    });
                },
                count: function () {
                    return Math.max(1, Math.ceil(this.search.length / this.size));
                },
                start: function () {
                    var s = (this.page - 1) * this.size;
                    if (s < 0) s = 0;
                    return s;
                }
            }
        });
    </script>
<?php }
?>
