// لوحة تحكم روح ينبع على GitHub Pages: تقرأ data/site.json وتحفظ التعديلات والصور مباشرة في مستودع GitHub عبر الـ API.
(function () {
    'use strict';

    var OWNER = 'jf26tvtc-star';
    var REPO = 'ynbb';
    var BRANCH = 'main';
    var DATA_PATH = 'data/site.json';
    var API = 'https://api.github.com/repos/' + OWNER + '/' + REPO;
    var TOKEN_KEY = 'yanbu_admin_token';
    var MAX_IMAGES = 10;
    var TOKEN_URL = 'https://github.com/settings/personal-access-tokens/new?name=' +
        encodeURIComponent('Yanbu site admin') + '&description=' + encodeURIComponent('Admin panel for jf26tvtc-star.github.io/ynbb') +
        '&target_name=' + OWNER + '&expires_in=366&contents=write';

    var TYPE_KEYWORDS = [
        ['بحرية', ['بحر', 'شاطئ', 'شواطئ', 'كورنيش', 'مارينا']],
        ['تاريخية', ['تاريخ', 'معلم', 'معالم', 'أثر', 'قلعة', 'تراث']],
        ['طبيعية', ['طبيع', 'جبل', 'جبال', 'حديقة', 'واحة']],
        ['ترفيهية', ['ترفيه', 'مطعم', 'مطاعم', 'فندق', 'فنادق', 'منتزه']]
    ];
    var TYPE_ICONS = { 'بحرية': '🏖️', 'تاريخية': '🏛️', 'طبيعية': '🏔️', 'ترفيهية': '🎡', 'عام': '📍' };
    var TYPE_SLUGS = { 'بحرية': 'sea', 'تاريخية': 'history', 'طبيعية': 'nature', 'ترفيهية': 'fun', 'عام': 'general' };

    var token = null;
    var data = null;
    var previews = {};   // معاينات الصور المرفوعة في هذه الجلسة (قبل أن ينشرها GitHub Pages)
    var draftImages = []; // الصور الجديدة المختارة في نموذج المكان

    // ===================== أدوات عامة =====================
    function $(id) { return document.getElementById(id); }

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function imgSrc(name) {
        if (!name) return 'images/placeholder.svg';
        return previews[name] || ('images/' + encodeURIComponent(name));
    }

    function toast(message, isError) {
        var el = $('toast');
        el.textContent = message;
        el.className = 'panel-toast' + (isError ? ' is-error' : '');
        el.hidden = false;
        clearTimeout(toast.timer);
        toast.timer = setTimeout(function () { el.hidden = true; }, isError ? 7000 : 4500);
    }

    function setBusy(on, text) {
        $('busy-text').textContent = text || 'جارٍ الحفظ...';
        $('busy').hidden = !on;
    }

    function today() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function phpUrlencode(text) {
        return encodeURIComponent(text).replace(/%20/g, '+').replace(/[!'()*~]/g, function (c) {
            return '%' + c.charCodeAt(0).toString(16).toUpperCase();
        });
    }

    // نفس منطق db.php: تحديد نوع التصنيف وأيقونته ولونه من اسمه
    function categoryType(name) {
        for (var i = 0; i < TYPE_KEYWORDS.length; i++) {
            if (TYPE_KEYWORDS[i][1].some(function (kw) { return name.indexOf(kw) !== -1; })) return TYPE_KEYWORDS[i][0];
        }
        return 'عام';
    }

    function mapsQuery(name, location) {
        return phpUrlencode((name + ' ' + location + ' ينبع').trim());
    }

    function autoMapsUrl(name, location) {
        return 'https://www.google.com/maps/search/?api=1&query=' + mapsQuery(name, location);
    }

    // إعادة حساب الحقول المشتقة (الأيقونات، الروابط، الأعداد، الإحصائيات) بعد أي تعديل
    function normalize(d) {
        var catById = {};
        d.categories.forEach(function (c) {
            var type = categoryType(c.name);
            c.type = type;
            c.slug = TYPE_SLUGS[type];
            c.icon = TYPE_ICONS[type];
            catById[c.id] = c;
        });
        d.places.forEach(function (p) {
            var cat = catById[p.category_id];
            if (cat) p.category = cat.name;
            var type = categoryType(p.category);
            if (p.google_maps_url === undefined) {
                p.google_maps_url = p.maps_url && p.maps_url !== autoMapsUrl(p.name, p.location) ? p.maps_url : '';
            }
            p.type = type;
            p.slug = TYPE_SLUGS[type];
            p.icon = TYPE_ICONS[type];
            p.maps_embed = 'https://www.google.com/maps?q=' + mapsQuery(p.name, p.location) + '&output=embed';
            p.maps_url = p.google_maps_url || autoMapsUrl(p.name, p.location);
            p.images = p.images || [];
            p.phone = p.phone || '';
        });
        d.places.sort(function (a, b) { return b.created_at.localeCompare(a.created_at) || b.id - a.id; });
        var imageCount = 0;
        d.places.forEach(function (p) { imageCount += p.images.length; });
        d.categories.forEach(function (c) {
            var list = d.places.filter(function (p) { return p.category_id === c.id; });
            var withImage = list.filter(function (p) { return p.images.length; })[0];
            c.places_count = list.length;
            c.cover = withImage ? withImage.images[0] : '';
        });
        var heroPlace = d.places.filter(function (p) { return p.images.length; })[0];
        d.hero_image = heroPlace ? heroPlace.images[0] : '';
        d.stats = { places: d.places.length, categories: d.categories.length, images: imageCount };
        d.generated_at = new Date().toISOString();
        return d;
    }

    // ===================== GitHub API =====================
    function gh(path, options) {
        options = options || {};
        var headers = {
            'Accept': 'application/vnd.github+json',
            'Authorization': 'Bearer ' + token,
            'X-GitHub-Api-Version': '2022-11-28'
        };
        if (options.body) headers['Content-Type'] = 'application/json';
        return fetch(API + path, {
            method: options.method || 'GET',
            headers: headers,
            body: options.body ? JSON.stringify(options.body) : undefined,
            cache: 'no-store'
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (json) {
                if (!res.ok) {
                    var err = new Error(json.message || ('HTTP ' + res.status));
                    err.status = res.status;
                    throw err;
                }
                return json;
            });
        });
    }

    function friendlyError(err) {
        if (err.status === 401) return 'مفتاح الدخول غير صالح أو منتهي الصلاحية. سجّل الدخول بمفتاح جديد.';
        if (err.status === 403) return 'المفتاح لا يملك صلاحية التعديل. تأكد أن صلاحية Contents هي Read and write على مستودع ynbb.';
        if (err.status === 404) return 'المفتاح لا يملك صلاحية الوصول إلى مستودع ynbb. اختر المستودع عند إنشاء المفتاح.';
        if (err.userMessage) return err.message;
        if (err instanceof TypeError) return 'تعذّر الاتصال بـ GitHub. تأكد من اتصالك بالإنترنت.';
        return 'حدث خطأ: ' + err.message;
    }

    function base64ToText(b64) {
        var bin = atob(b64.replace(/\s/g, ''));
        var bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return new TextDecoder('utf-8').decode(bytes);
    }

    async function fetchLatest() {
        var ref = await gh('/git/ref/heads/' + BRANCH);
        var head = ref.object.sha;
        var file = await gh('/contents/' + DATA_PATH + '?ref=' + head);
        var text;
        if (file.content && file.encoding === 'base64') {
            text = base64ToText(file.content);
        } else {
            var blob = await gh('/git/blobs/' + file.sha);
            text = base64ToText(blob.content);
        }
        return { head: head, data: JSON.parse(text) };
    }

    async function existingImages(head) {
        var list = await gh('/contents/images?ref=' + head);
        var names = {};
        list.forEach(function (f) { names[f.name] = true; });
        return names;
    }

    function userError(message) {
        var err = new Error(message);
        err.userMessage = true;
        return err;
    }

    // حفظ تعديل كـ commit واحد: يرفع الصور الجديدة، ثم يطبّق التعديل على أحدث نسخة من البيانات (لتجنب فقدان تعديلات أخرى)
    async function commitChange(message, mutate, newFiles) {
        var uploaded = [];
        for (var i = 0; i < (newFiles || []).length; i++) {
            var blob = await gh('/git/blobs', { method: 'POST', body: { content: newFiles[i].base64, encoding: 'base64' } });
            uploaded.push({ path: 'images/' + newFiles[i].name, sha: blob.sha });
        }
        for (var attempt = 0; attempt < 3; attempt++) {
            var latest = await fetchLatest();
            var next = JSON.parse(JSON.stringify(latest.data));
            normalize(next);
            var result = mutate(next) || {};
            normalize(next);

            var tree = [{ path: DATA_PATH, mode: '100644', type: 'blob', content: JSON.stringify(next, null, 4) + '\n' }];
            uploaded.forEach(function (f) { tree.push({ path: f.path, mode: '100644', type: 'blob', sha: f.sha }); });
            var removals = result.removeImages || [];
            if (removals.length) {
                var present = await existingImages(latest.head);
                removals.forEach(function (name) {
                    if (present[name] && name !== 'placeholder.svg') {
                        tree.push({ path: 'images/' + name, mode: '100644', type: 'blob', sha: null });
                    }
                });
            }

            var headCommit = await gh('/git/commits/' + latest.head);
            var newTree = await gh('/git/trees', { method: 'POST', body: { base_tree: headCommit.tree.sha, tree: tree } });
            var commit = await gh('/git/commits', { method: 'POST', body: { message: message, tree: newTree.sha, parents: [latest.head] } });
            try {
                await gh('/git/refs/heads/' + BRANCH, { method: 'PATCH', body: { sha: commit.sha, force: false } });
                data = next;
                updateCounts();
                $('sync-status').textContent = 'آخر حفظ: ' + new Date().toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' });
                return result;
            } catch (err) {
                // تغيّر المستودع أثناء الحفظ (تعديل من جهاز آخر): نعيد المحاولة على أحدث نسخة
                if (err.status !== 422 && err.status !== 409) throw err;
            }
        }
        throw userError('تعذّر الحفظ بسبب تعديل متزامن من مكان آخر. حدّث الصفحة وحاول مرة أخرى.');
    }

    function unusedImages(d, names) {
        return names.filter(function (name) {
            return !d.places.some(function (p) { return p.images.indexOf(name) !== -1; });
        });
    }

    // ===================== الصور =====================
    function prepareImage(file) {
        return new Promise(function (resolve, reject) {
            if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
                reject(userError('الصورة "' + file.name + '" يجب أن تكون JPG أو PNG أو WEBP.'));
                return;
            }
            if (file.size > 15 * 1024 * 1024) {
                reject(userError('الصورة "' + file.name + '" أكبر من 15MB.'));
                return;
            }
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () {
                // تصغير الصورة قبل الرفع لتسريع الموقع وتقليل حجم المستودع
                var max = 1600;
                var scale = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
                var canvas = document.createElement('canvas');
                canvas.width = Math.round(img.naturalWidth * scale);
                canvas.height = Math.round(img.naturalHeight * scale);
                var ctx = canvas.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                URL.revokeObjectURL(url);
                canvas.toBlob(function (blob) {
                    if (!blob) { reject(userError('تعذّر تجهيز الصورة "' + file.name + '".')); return; }
                    var reader = new FileReader();
                    reader.onload = function () {
                        var rand = Math.random().toString(16).slice(2, 8);
                        resolve({
                            name: 'place_' + Date.now().toString(36) + '_' + rand + '.jpg',
                            base64: String(reader.result).split(',')[1],
                            preview: URL.createObjectURL(blob)
                        });
                    };
                    reader.readAsDataURL(blob);
                }, 'image/jpeg', 0.82);
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                reject(userError('تعذّر قراءة الصورة "' + file.name + '".'));
            };
            img.src = url;
        });
    }

    // ===================== العرض =====================
    var TITLES = { overview: 'نظرة عامة', places: 'إدارة الأماكن', 'place-new': 'إضافة مكان', 'place-edit': 'تعديل مكان', categories: 'التصنيفات', messages: 'رسائل الزوار' };

    function updateCounts() {
        $('nav-places').textContent = data.places.length;
        $('nav-categories').textContent = data.categories.length;
    }

    function route() {
        if (!data) return;
        var hash = window.location.hash.replace('#', '') || 'overview';
        var match = hash.match(/^place-edit-(\d+)$/);
        var view = match ? 'place-edit' : hash;
        if (!TITLES[view]) view = 'overview';
        $('crumb').textContent = TITLES[view];
        document.title = TITLES[view] + ' - لوحة التحكم - روح ينبع';
        document.querySelectorAll('[data-nav]').forEach(function (a) {
            var active = a.getAttribute('data-nav') === view || (view === 'place-edit' && a.getAttribute('data-nav') === 'places');
            a.classList.toggle('is-active', active);
            if (active) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
        });
        draftImages = [];
        var render = {
            overview: renderOverview,
            places: renderPlaces,
            'place-new': function () { renderPlaceForm(null); },
            'place-edit': function () { renderPlaceForm(parseInt(match[1], 10)); },
            categories: renderCategories,
            messages: renderMessages
        }[view];
        render();
        $('view').focus({ preventScroll: true });
        window.scrollTo(0, 0);
    }

    function heading(title, sub, action) {
        return '<div class="dash-page-heading"><div><p class="dash-eyebrow">لوحة تحكم روح ينبع</p><h1>' + esc(title) + '</h1>' +
            (sub ? '<p>' + esc(sub) + '</p>' : '') + '</div>' + (action || '') + '</div>';
    }

    function placeRow(p) {
        return '<tr><td><div class="dash-place-cell"><img src="' + esc(imgSrc(p.images[0])) + '" alt="" width="46" height="46" loading="lazy">' +
            '<div><a href="details.html?id=' + p.id + '" target="_blank" rel="noopener">' + esc(p.name) + '</a><small>' + esc(p.location) + '</small></div></div></td>' +
            '<td><span class="dash-category-badge">' + esc(p.category) + '</span></td>' +
            '<td><time>' + esc(p.created_at) + '</time></td>' +
            '<td><div class="panel-row-actions"><a class="panel-link" href="#place-edit-' + p.id + '">تعديل</a>' +
            '<button type="button" class="panel-link is-danger" data-delete-place="' + p.id + '">حذف</button></div></td></tr>';
    }

    function placesTable(list) {
        if (!list.length) {
            return '<div class="dash-empty"><h3>لا توجد أماكن</h3><p>أضف أول وجهة ليبدأ الزوار باكتشاف جمال ينبع.</p>' +
                '<a class="dash-button dash-button-primary" href="#place-new">إضافة مكان</a></div>';
        }
        return '<div class="dash-table-wrap"><table class="dash-table"><thead><tr><th scope="col">المكان</th><th scope="col">التصنيف</th>' +
            '<th scope="col">تاريخ الإضافة</th><th scope="col">الإجراءات</th></tr></thead><tbody>' +
            list.map(placeRow).join('') + '</tbody></table></div>';
    }

    function renderOverview() {
        var s = data.stats;
        $('view').innerHTML = heading('لوحة التحكم', 'كل ما تعدّله هنا يُنشر على الموقع تلقائيًا.') +
            '<section class="dash-welcome"><div class="dash-welcome-content"><span class="dash-welcome-label"><span></span> مساحة إدارة روح ينبع</span>' +
            '<h2>أهلًا بك، رحلة أجمل تبدأ من هنا</h2><p>أضف الوجهات وصورها ورتّب التصنيفات من أي جهاز.<br>تظهر التعديلات للزوار خلال دقيقة إلى دقيقتين.</p>' +
            '<div class="dash-welcome-actions"><a href="#place-new" class="dash-button dash-button-gold">+ إضافة مكان جديد</a>' +
            '<a href="index.html" target="_blank" rel="noopener" class="dash-hero-link">استعراض الموقع ←</a></div></div></section>' +
            '<section class="dash-stats panel-stats">' +
            '<a class="dash-stat" href="#places"><span class="dash-stat-label">إجمالي الأماكن</span><strong>' + s.places + '</strong><span class="dash-stat-foot">إدارة الأماكن ←</span></a>' +
            '<a class="dash-stat" href="#categories"><span class="dash-stat-label">التصنيفات</span><strong>' + s.categories + '</strong><span class="dash-stat-foot">إدارة التصنيفات ←</span></a>' +
            '<a class="dash-stat" href="#places"><span class="dash-stat-label">الصور</span><strong>' + s.images + '</strong><span class="dash-stat-foot">صور الوجهات ←</span></a>' +
            '</section>' +
            '<section class="dash-panel"><div class="dash-panel-heading"><div><h2>آخر الأماكن المضافة</h2><p>أحدث 5 أماكن في الدليل</p></div>' +
            '<a class="dash-text-link" href="#places">عرض الكل ←</a></div>' + placesTable(data.places.slice(0, 5)) + '</section>';
    }

    function renderPlaces() {
        $('view').innerHTML = heading('إدارة الأماكن', data.places.length + ' مكان في الدليل',
            '<a class="dash-button dash-button-primary" href="#place-new">+ إضافة مكان</a>') +
            '<section class="dash-panel"><div class="dash-search panel-search"><div class="dash-search-input">' +
            '<label class="dash-sr-only" for="place-filter">ابحث في الأماكن</label>' +
            '<input id="place-filter" type="search" placeholder="ابحث باسم المكان أو الموقع..."></div></div><div id="places-table"></div></section>';
        var draw = function () {
            var q = $('place-filter').value.trim().toLowerCase();
            $('places-table').innerHTML = placesTable(data.places.filter(function (p) {
                return !q || p.name.toLowerCase().indexOf(q) !== -1 || p.location.toLowerCase().indexOf(q) !== -1;
            }));
        };
        $('place-filter').addEventListener('input', draw);
        draw();
    }

    function renderPlaceForm(id) {
        var place = id ? data.places.filter(function (p) { return p.id === id; })[0] : null;
        if (id && !place) {
            $('view').innerHTML = heading('المكان غير موجود', 'ربما تم حذفه.') + '<a class="dash-button dash-button-primary" href="#places">العودة للأماكن</a>';
            return;
        }
        if (!data.categories.length) {
            $('view').innerHTML = heading('أضف تصنيفًا أولًا', 'يجب أن يكون هناك تصنيف واحد على الأقل قبل إضافة الأماكن.') +
                '<a class="dash-button dash-button-primary" href="#categories">إدارة التصنيفات</a>';
            return;
        }
        var p = place || { name: '', category_id: data.categories[0].id, location: '', google_maps_url: '', phone: '', description: '', images: [] };
        var keptImages = p.images.slice();

        $('view').innerHTML = heading(place ? 'تعديل: ' + place.name : 'إضافة مكان جديد', place ? 'عدّل البيانات ثم اضغط حفظ.' : 'أدخل بيانات الوجهة وصورها.') +
            '<form class="dash-panel panel-form" id="place-form" novalidate>' +
            '<div class="panel-grid">' +
            '<label>اسم المكان *<input name="name" required maxlength="150" value="' + esc(p.name) + '"></label>' +
            '<label>التصنيف *<select name="category_id" required>' + data.categories.map(function (c) {
                return '<option value="' + c.id + '"' + (c.id === p.category_id ? ' selected' : '') + '>' + esc(c.name) + '</option>';
            }).join('') + '</select></label>' +
            '<label>الموقع *<input name="location" required maxlength="200" value="' + esc(p.location) + '" placeholder="مثال: ينبع البحر - الكورنيش"></label>' +
            '<label>رقم الهاتف (اختياري)<input name="phone" maxlength="30" dir="ltr" value="' + esc(p.phone) + '"></label>' +
            '</div>' +
            '<label>رابط خرائط قوقل (اختياري — يُولَّد تلقائيًا إن تُرك فارغًا)<input name="google_maps_url" type="url" maxlength="500" dir="ltr" value="' + esc(p.google_maps_url) + '" placeholder="https://maps.app.goo.gl/..."></label>' +
            '<label>وصف المكان *<textarea name="description" required rows="7">' + esc(p.description) + '</textarea></label>' +
            '<fieldset class="panel-images"><legend>الصور (أول صورة هي الصورة الرئيسية)</legend><div id="image-list" class="panel-image-list"></div>' +
            '<label class="panel-upload">+ إضافة صور<input type="file" id="image-input" accept="image/jpeg,image/png,image/webp" multiple hidden></label>' +
            '<p class="panel-muted">تُصغَّر الصور تلقائيًا قبل الرفع. الحد الأقصى ' + MAX_IMAGES + ' صور لكل مكان.</p></fieldset>' +
            '<div class="alert alert-danger" id="form-error" hidden></div>' +
            '<div class="panel-actions"><button class="dash-button dash-button-primary" type="submit">' + (place ? 'حفظ التعديلات' : 'إضافة المكان') + '</button>' +
            '<a class="dash-button panel-secondary" href="#places">إلغاء</a></div></form>';

        function drawImages() {
            var items = keptImages.map(function (name, i) {
                return '<div class="panel-image"><img src="' + esc(imgSrc(name)) + '" alt="">' + (i === 0 ? '<span class="panel-badge">رئيسية</span>' : '') +
                    '<div>' + (i === 0 ? '' : '<button type="button" data-main="' + i + '">اجعلها رئيسية</button>') +
                    '<button type="button" class="is-danger" data-remove="' + i + '">حذف</button></div></div>';
            }).concat(draftImages.map(function (img, i) {
                var first = keptImages.length === 0 && i === 0;
                return '<div class="panel-image is-new"><img src="' + esc(img.preview) + '" alt="">' + (first ? '<span class="panel-badge">رئيسية</span>' : '<span class="panel-badge is-new">جديدة</span>') +
                    '<div><button type="button" class="is-danger" data-remove-new="' + i + '">إزالة</button></div></div>';
            }));
            $('image-list').innerHTML = items.length ? items.join('') : '<p class="panel-muted">لا توجد صور بعد — ستظهر صورة افتراضية.</p>';
        }
        drawImages();

        $('image-list').addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn) return;
            if (btn.hasAttribute('data-main')) {
                var i = +btn.getAttribute('data-main');
                keptImages.unshift(keptImages.splice(i, 1)[0]);
            } else if (btn.hasAttribute('data-remove')) {
                keptImages.splice(+btn.getAttribute('data-remove'), 1);
            } else if (btn.hasAttribute('data-remove-new')) {
                draftImages.splice(+btn.getAttribute('data-remove-new'), 1);
            }
            drawImages();
        });

        $('image-input').addEventListener('change', async function (e) {
            var files = Array.prototype.slice.call(e.target.files);
            e.target.value = '';
            if (keptImages.length + draftImages.length + files.length > MAX_IMAGES) {
                toast('الحد الأقصى ' + MAX_IMAGES + ' صور لكل مكان.', true);
                return;
            }
            setBusy(true, 'جارٍ تجهيز الصور...');
            try {
                for (var i = 0; i < files.length; i++) draftImages.push(await prepareImage(files[i]));
            } catch (err) {
                toast(friendlyError(err), true);
            }
            setBusy(false);
            drawImages();
        });

        $('place-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            var form = e.target;
            var field = function (n) { return form.elements.namedItem(n); };
            var errorBox = $('form-error');
            errorBox.hidden = true;
            var values = {
                name: field('name').value.trim(),
                category_id: parseInt(field('category_id').value, 10),
                location: field('location').value.trim(),
                phone: field('phone').value.trim(),
                google_maps_url: field('google_maps_url').value.trim(),
                description: field('description').value.trim()
            };
            var problem = !values.name || !values.location || !values.description ? 'يرجى تعبئة الحقول المطلوبة (الاسم، الموقع، الوصف).'
                : values.google_maps_url && !/^https:\/\/[^\s"'<>]+$/i.test(values.google_maps_url) ? 'رابط خرائط قوقل يجب أن يبدأ بـ https://'
                : '';
            if (problem) {
                errorBox.textContent = problem;
                errorBox.hidden = false;
                return;
            }
            var newImages = draftImages.slice();
            var images = keptImages.concat(newImages.map(function (img) { return img.name; }));
            setBusy(true, 'جارٍ الحفظ ورفع التعديلات...');
            try {
                var savedId = await commitChange((place ? 'Edit place: ' : 'Add place: ') + values.name, function (d) {
                    if (!d.categories.some(function (c) { return c.id === values.category_id; })) {
                        throw userError('التصنيف المختار لم يعد موجودًا. حدّث الصفحة.');
                    }
                    var target;
                    var removed = [];
                    if (place) {
                        target = d.places.filter(function (x) { return x.id === place.id; })[0];
                        if (!target) throw userError('هذا المكان حُذف من مكان آخر.');
                        removed = target.images.filter(function (name) { return images.indexOf(name) === -1; });
                    } else {
                        target = { id: d.places.reduce(function (m, x) { return Math.max(m, x.id); }, 0) + 1, created_at: today() };
                        d.places.push(target);
                    }
                    Object.keys(values).forEach(function (k) { target[k] = values[k]; });
                    target.images = images;
                    return { id: target.id, removeImages: unusedImages(d, removed) };
                }, newImages);
                newImages.forEach(function (img) { previews[img.name] = img.preview; });
                draftImages = [];
                toast(place ? 'تم حفظ التعديلات ✓ ستظهر على الموقع خلال دقيقة إلى دقيقتين.' : 'تمت إضافة المكان ✓ سيظهر على الموقع خلال دقيقة إلى دقيقتين.');
                window.location.hash = '#place-edit-' + savedId.id;
                if (place) route();
            } catch (err) {
                errorBox.textContent = friendlyError(err);
                errorBox.hidden = false;
                if (err.status === 401) logout();
            }
            setBusy(false);
        });
    }

    async function deletePlace(id) {
        var place = data.places.filter(function (p) { return p.id === id; })[0];
        if (!place || !window.confirm('حذف "' + place.name + '" وصوره نهائيًا؟')) return;
        setBusy(true, 'جارٍ الحذف...');
        try {
            await commitChange('Delete place: ' + place.name, function (d) {
                var target = d.places.filter(function (x) { return x.id === id; })[0];
                if (!target) return {};
                d.places = d.places.filter(function (x) { return x.id !== id; });
                return { removeImages: unusedImages(d, target.images) };
            });
            toast('تم حذف المكان ✓');
            route();
        } catch (err) {
            toast(friendlyError(err), true);
        }
        setBusy(false);
    }

    function renderCategories() {
        $('view').innerHTML = heading('التصنيفات', 'نظّم الأماكن في تصنيفات يسهل على الزائر تصفحها.') +
            '<div class="panel-columns"><section class="dash-panel"><div class="dash-panel-heading"><div><h2>كل التصنيفات</h2></div></div>' +
            '<div class="dash-table-wrap"><table class="dash-table"><thead><tr><th scope="col">الاسم</th><th scope="col">الوصف</th><th scope="col">الأماكن</th><th scope="col">الإجراءات</th></tr></thead><tbody>' +
            (data.categories.length ? data.categories.map(function (c) {
                return '<tr><td><strong>' + esc(c.icon) + ' ' + esc(c.name) + '</strong></td><td>' + esc(c.description) + '</td><td>' + c.places_count + '</td>' +
                    '<td><div class="panel-row-actions"><button type="button" class="panel-link" data-edit-cat="' + c.id + '">تعديل</button>' +
                    '<button type="button" class="panel-link is-danger" data-delete-cat="' + c.id + '"' + (c.places_count ? ' disabled title="لا يمكن حذف تصنيف مرتبط بأماكن"' : '') + '>حذف</button></div></td></tr>';
            }).join('') : '<tr><td colspan="4">لا توجد تصنيفات بعد.</td></tr>') +
            '</tbody></table></div></section>' +
            '<form class="dash-panel panel-form" id="cat-form"><h2 id="cat-form-title">إضافة تصنيف</h2><input type="hidden" name="id" value="">' +
            '<label>اسم التصنيف *<input name="name" required maxlength="100"></label>' +
            '<label>وصف مختصر (اختياري)<input name="description" maxlength="255"></label>' +
            '<p class="panel-muted">تُختار الأيقونة واللون تلقائيًا من الاسم (مثل: شواطئ، معالم، جبال، مطاعم).</p>' +
            '<div class="alert alert-danger" id="cat-error" hidden></div>' +
            '<div class="panel-actions"><button class="dash-button dash-button-primary" type="submit">حفظ</button>' +
            '<button class="dash-button panel-secondary" type="reset" id="cat-reset">إلغاء</button></div></form></div>';

        var form = $('cat-form');
        var field = function (n) { return form.elements.namedItem(n); };
        form.addEventListener('reset', function () {
            setTimeout(function () { field('id').value = ''; $('cat-form-title').textContent = 'إضافة تصنيف'; $('cat-error').hidden = true; });
        });
        $('view').querySelectorAll('[data-edit-cat]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var c = data.categories.filter(function (x) { return x.id === +btn.getAttribute('data-edit-cat'); })[0];
                field('id').value = c.id;
                field('name').value = c.name;
                field('description').value = c.description;
                $('cat-form-title').textContent = 'تعديل: ' + c.name;
                field('name').focus();
            });
        });
        $('view').querySelectorAll('[data-delete-cat]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                var id = +btn.getAttribute('data-delete-cat');
                var c = data.categories.filter(function (x) { return x.id === id; })[0];
                if (!c || !window.confirm('حذف تصنيف "' + c.name + '"؟')) return;
                setBusy(true, 'جارٍ الحذف...');
                try {
                    await commitChange('Delete category: ' + c.name, function (d) {
                        if (d.places.some(function (p) { return p.category_id === id; })) throw userError('لا يمكن حذف تصنيف مرتبط بأماكن.');
                        d.categories = d.categories.filter(function (x) { return x.id !== id; });
                    });
                    toast('تم حذف التصنيف ✓');
                    route();
                } catch (err) {
                    toast(friendlyError(err), true);
                }
                setBusy(false);
            });
        });
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            var id = parseInt(field('id').value, 10) || 0;
            var name = field('name').value.trim();
            var description = field('description').value.trim();
            var errorBox = $('cat-error');
            if (!name) { errorBox.textContent = 'يرجى إدخال اسم التصنيف.'; errorBox.hidden = false; return; }
            setBusy(true);
            try {
                await commitChange((id ? 'Edit category: ' : 'Add category: ') + name, function (d) {
                    if (d.categories.some(function (c) { return c.name === name && c.id !== id; })) throw userError('هذا التصنيف موجود مسبقًا.');
                    if (id) {
                        var target = d.categories.filter(function (c) { return c.id === id; })[0];
                        if (!target) throw userError('هذا التصنيف حُذف من مكان آخر.');
                        target.name = name;
                        target.description = description;
                    } else {
                        var nextId = d.categories.reduce(function (m, c) { return Math.max(m, c.id); }, 0) + 1;
                        d.categories.push({ id: nextId, name: name, description: description });
                    }
                });
                toast(id ? 'تم تعديل التصنيف ✓' : 'تمت إضافة التصنيف ✓');
                route();
            } catch (err) {
                errorBox.textContent = friendlyError(err);
                errorBox.hidden = false;
            }
            setBusy(false);
        });
    }

    function renderMessages() {
        $('view').innerHTML = heading('رسائل الزوار', 'تصل رسائل نموذج "تواصل معنا" إلى بريدك الإلكتروني.') +
            '<section class="dash-panel panel-info"><h2>أين أجد الرسائل؟</h2>' +
            '<p>كل رسالة يرسلها زائر من صفحة التواصل تصل مباشرة إلى <b dir="ltr">jf26tvtc@gmail.com</b> عبر خدمة FormSubmit، ويمكنك الرد عليها من بريدك.</p>' +
            '<p>أول مرة فقط: سيصلك بريد تفعيل من FormSubmit — اضغط <b>Activate</b> فيه لتبدأ الرسائل بالوصول.</p>' +
            '<div class="panel-actions"><a class="dash-button dash-button-primary" href="https://mail.google.com/mail/u/0/#search/formsubmit" target="_blank" rel="noopener">فتح الرسائل في Gmail</a>' +
            '<a class="dash-button panel-secondary" href="contact.html" target="_blank" rel="noopener">صفحة التواصل</a></div></section>';
    }

    // ===================== الدخول والخروج =====================
    function storedToken() {
        try { return sessionStorage.getItem(TOKEN_KEY) || localStorage.getItem(TOKEN_KEY); } catch (e) { return null; }
    }

    function logout() {
        try { sessionStorage.removeItem(TOKEN_KEY); localStorage.removeItem(TOKEN_KEY); } catch (e) { /* التخزين غير متاح */ }
        token = null;
        data = null;
        $('app-view').hidden = true;
        $('login-view').hidden = false;
        $('token-input').value = '';
    }

    async function start(candidate, remember, fromForm) {
        token = candidate;
        var errorBox = $('login-error');
        errorBox.hidden = true;
        setBusy(true, 'جارٍ تحميل البيانات...');
        try {
            var user = await fetch('https://api.github.com/user', {
                headers: { 'Accept': 'application/vnd.github+json', 'Authorization': 'Bearer ' + token },
                cache: 'no-store'
            }).then(function (res) { return res.ok ? res.json() : {}; });
            var latest = await fetchLatest();
            data = normalize(latest.data);
            try {
                sessionStorage.setItem(TOKEN_KEY, token);
                if (remember) localStorage.setItem(TOKEN_KEY, token);
                else if (fromForm) localStorage.removeItem(TOKEN_KEY);
            } catch (e) { /* التخزين غير متاح: يبقى الدخول لهذه الصفحة فقط */ }
            $('profile-name').textContent = user.login || 'المسؤول';
            $('login-view').hidden = true;
            $('app-view').hidden = false;
            updateCounts();
            route();
        } catch (err) {
            logout();
            errorBox.textContent = friendlyError(err);
            errorBox.hidden = false;
        }
        setBusy(false);
    }

    document.addEventListener('DOMContentLoaded', function () {
        $('token-link').href = TOKEN_URL;
        $('login-form').addEventListener('submit', function (e) {
            e.preventDefault();
            var value = $('token-input').value.trim();
            if (value) start(value, $('remember-input').checked, true);
        });
        $('logout-link').addEventListener('click', function (e) {
            e.preventDefault();
            logout();
        });
        $('view').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-delete-place]');
            if (btn) deletePlace(+btn.getAttribute('data-delete-place'));
        });
        window.addEventListener('hashchange', route);

        var saved = storedToken();
        if (saved) start(saved, false, false);
        else $('login-view').hidden = false;
    });
})();
