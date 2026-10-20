// روح ينبع — النسخة الثابتة للموقع (GitHub Pages): تقرأ البيانات من data/site.json وتعرض الصفحات في المتصفح.
(function () {
    'use strict';

    var CONTACT_EMAIL = 'jf26tvtc@gmail.com';
    var PER_PAGE = 9;

    var TYPE_LABELS = {
        'بحرية': { title: '🏖️ وجهات بحرية', sub: 'شواطئ وكورنيش ينبع الساحرة' },
        'تاريخية': { title: '🏛️ معالم تاريخية', sub: 'آثار وتراث يحكي قصة ينبع' },
        'طبيعية': { title: '🏔️ وجهات طبيعية', sub: 'جبال وطبيعة خلّابة' },
        'ترفيهية': { title: '🎡 مطاعم وترفيه', sub: 'أفضل المطاعم والفنادق وأماكن الترفيه' }
    };

    var DESTINATIONS = {
        sea: {
            name: 'ينبع البحر',
            intro: 'اكتشف الوجه الساحلي لينبع، بين البحر الأحمر وأجواء المدينة القديمة.',
            activities: [
                ['البحر والكورنيش', 'خصص وقتاً للمشي على الواجهة البحرية والاستمتاع بمنظر البحر، خاصة وقت الغروب.'],
                ['التراث والأسواق', 'تعرّف على أجواء ينبع التاريخية وتجول في أحيائها وأسواقها القديمة.'],
                ['جلسة على الساحل', 'اختم جولتك بوجبة أو جلسة قريبة من البحر، وتحقق من مواعيد المكان قبل الزيارة.']
            ]
        },
        nakhl: {
            name: 'ينبع النخل',
            intro: 'وجهة لمحبي الطبيعة والهدوء، تجمع أجواء القرى والنخيل والتراث.',
            activities: [
                ['الطبيعة والنخيل', 'استمتع بمشاهدة الطبيعة واختر وقتاً مناسباً للطقس لجولتك.'],
                ['القرى والتراث', 'اكتشف ملامح الحياة الريفية والتراث المحلي مع احترام خصوصية السكان والمزارع.'],
                ['رحلة هادئة', 'جهّز الماء واحتياجات الطريق مسبقاً، وتأكد من مسارك وإمكانية الوصول قبل الانطلاق.']
            ]
        },
        industrial: {
            name: 'ينبع الصناعية',
            intro: 'اكتشف الواجهات البحرية والمساحات الخضراء والأجواء العائلية في ينبع الصناعية.',
            activities: [
                ['الواجهة البحرية', 'ابدأ جولتك بالمشي والاستمتاع بإطلالة البحر في الأوقات المعتدلة.'],
                ['الحدائق والمساحات الخضراء', 'خصص وقتاً للاسترخاء أو نزهة عائلية، وتحقق من تعليمات ومواعيد الموقع الذي تختاره.'],
                ['مطاعم وترفيه', 'اختر من الأماكن المسجلة في الدليل ما يناسب اهتماماتك ووقت رحلتك.']
            ]
        }
    };

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function imageUrl(path) {
        return 'images/' + encodeURIComponent(path || 'placeholder.svg');
    }

    function params() {
        return new URLSearchParams(window.location.search);
    }

    function byId(id) {
        return document.getElementById(id);
    }

    var dataPromise = null;
    function loadData() {
        if (!dataPromise) {
            // no-cache: يتحقق المتصفح من وجود نسخة أحدث بعد كل نشر
            dataPromise = fetch('data/site.json', { cache: 'no-cache' }).then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            });
        }
        return dataPromise;
    }

    function showLoadError(target) {
        var el = target || byId('page-content');
        if (el) {
            el.innerHTML = '<p class="empty-note">تعذّر تحميل بيانات الموقع. تأكد من اتصالك بالإنترنت ثم حدّث الصفحة.</p>';
        }
    }

    function placeCard(p, withDescription) {
        var desc = '';
        if (withDescription) {
            var text = p.description.length > 100 ? p.description.slice(0, 100) + '...' : p.description;
            desc = '<p>' + esc(text) + '</p>';
        }
        var btnStyle = withDescription ? '' : ' style="padding:9px 20px;font-size:14px;"';
        return '<div class="place-card ' + esc(p.slug) + '">' +
            '<div class="img-wrap"><img src="' + imageUrl(p.images[0]) + '" alt="' + esc(p.name) + '" loading="lazy"></div>' +
            '<div class="place-card-content">' +
            '<span class="category ' + esc(p.slug) + '">' + esc(p.icon) + ' ' + esc(p.category) + '</span>' +
            '<h3>' + esc(p.name) + '</h3>' +
            '<p class="location">📍 ' + esc(p.location) + '</p>' +
            desc +
            '<a href="details.html?id=' + encodeURIComponent(p.id) + '" class="btn"' + btnStyle + '>عرض التفاصيل</a>' +
            '</div></div>';
    }

    function categoryOptions(categories, selected, firstLabel, withIcon) {
        var html = '<option value="">' + esc(firstLabel) + '</option>';
        categories.forEach(function (c) {
            html += '<option value="' + esc(c.name) + '"' + (c.name === selected ? ' selected' : '') + '>' +
                (withIcon ? esc(c.icon) + ' ' : '') + esc(c.name) + '</option>';
        });
        return html;
    }

    // ===================== الصفحة الرئيسية =====================
    function initHome() {
        loadData().then(function (data) {
            if (data.hero_image) {
                // رابط كامل: الروابط النسبية داخل متغيرات CSS تُحسب من مجلد css وليس من الصفحة
                var heroUrl = new URL(imageUrl(data.hero_image), window.location.href).href;
                byId('home-hero').style.setProperty('--hero-img', 'url("' + heroUrl + '")');
            }
            byId('home-category').innerHTML = categoryOptions(data.categories, '', 'كل التصنيفات', true);
            byId('home-pills').innerHTML = data.categories.map(function (c) {
                return '<a class="cat-pill" href="places.html?category=' + encodeURIComponent(c.name) + '">' +
                    '<span class="ico">' + esc(c.icon) + '</span>' + esc(c.name) +
                    ' <span style="color:var(--muted);font-weight:600;">(' + c.places_count + ')</span></a>';
            }).join('');

            var byType = { 'بحرية': [], 'تاريخية': [], 'طبيعية': [], 'ترفيهية': [] };
            data.places.forEach(function (p) {
                if (byType[p.type]) byType[p.type].push(p);
            });
            var sections = '';
            Object.keys(byType).forEach(function (type) {
                var list = byType[type];
                if (!list.length) return;
                sections += '<section class="container"><div class="section-head"><div>' +
                    '<h2>' + TYPE_LABELS[type].title + '</h2><p class="section-sub">' + TYPE_LABELS[type].sub + '</p></div>' +
                    '<a class="see-all" href="places.html?category=' + encodeURIComponent(list[0].category) + '">عرض الكل ←</a></div>' +
                    '<div class="scroll-row">' + list.slice(0, 8).map(function (p) { return placeCard(p, false); }).join('') + '</div></section>';
            });
            if (!data.places.length) {
                sections = '<section class="container"><p class="empty-note">لا توجد أماكن مسجلة حالياً.</p></section>';
            }
            byId('home-sections').innerHTML = sections;

            byId('stat-places').textContent = data.stats.places;
            byId('stat-categories').textContent = data.stats.categories;
            byId('stat-images').textContent = data.stats.images;
        }).catch(function () { showLoadError(byId('home-sections')); });
    }

    // ===================== صفحة الأماكن (بحث + تصفية + صفحات) =====================
    function initPlaces() {
        var q = params();
        var category = q.get('category') || '';
        var search = (q.get('search') || '').trim();
        var page = Math.max(1, parseInt(q.get('page'), 10) || 1);

        byId('search').value = search;
        if (category) {
            document.title = 'وجهات ' + category + ' - روح ينبع';
        }

        function go() {
            var next = new URLSearchParams();
            var s = byId('search').value.trim();
            var c = byId('category').value;
            if (s) next.set('search', s);
            if (c) next.set('category', c);
            window.location.href = 'places.html' + (next.toString() ? '?' + next.toString() : '');
        }
        byId('filter-btn').addEventListener('click', go);
        byId('search').addEventListener('keydown', function (e) { if (e.key === 'Enter') go(); });

        loadData().then(function (data) {
            byId('category').innerHTML = categoryOptions(data.categories, category, 'جميع الفئات', false);
            var selected = data.categories.filter(function (c) { return c.name === category; })[0];
            if (selected) {
                byId('places-title').textContent = selected.icon + ' وجهات ' + selected.name;
                byId('places-sub').textContent = 'تصفّح كل الأماكن المصنّفة ضمن "' + selected.name + '" في ينبع';
            }

            var needle = search.toLowerCase();
            var results = data.places.filter(function (p) {
                if (category && p.category !== category) return false;
                if (!needle) return true;
                return [p.name, p.description, p.location].some(function (field) {
                    return field.toLowerCase().indexOf(needle) !== -1;
                });
            });

            var totalPages = Math.max(1, Math.ceil(results.length / PER_PAGE));
            page = Math.min(page, totalPages);
            var slice = results.slice((page - 1) * PER_PAGE, page * PER_PAGE);
            byId('places-grid').innerHTML = slice.length
                ? slice.map(function (p) { return placeCard(p, true); }).join('')
                : '<p style="text-align:center; grid-column: 1/-1; padding: 40px;">لم يتم العثور على نتائج</p>';

            var pager = '';
            if (totalPages > 1) {
                for (var i = 1; i <= totalPages; i++) {
                    var link = new URLSearchParams();
                    link.set('page', i);
                    if (category) link.set('category', category);
                    if (search) link.set('search', search);
                    pager += '<a href="places.html?' + link.toString() + '" class="btn' + (i === page ? ' active' : '') + '" style="margin: 5px;">صفحة ' + i + '</a>';
                }
            }
            byId('places-pager').innerHTML = pager;
        }).catch(function () { showLoadError(byId('places-grid')); });
    }

    // ===================== صفحة تفاصيل المكان =====================
    function initDetails() {
        var id = parseInt(params().get('id'), 10);
        loadData().then(function (data) {
            var place = data.places.filter(function (p) { return p.id === id; })[0];
            if (!place) {
                byId('page-content').innerHTML = '<div style="text-align:center;padding:60px 0;"><h2>المكان غير موجود</h2>' +
                    '<p class="empty-note">ربما تم حذفه أو أن الرابط غير صحيح.</p><a class="btn" href="places.html">تصفّح الأماكن</a></div>';
                return;
            }
            document.title = place.name + ' - روح ينبع';
            byId('crumb-name').textContent = place.name;
            var images = place.images.length ? place.images : [''];

            var thumbs = '';
            if (images.length > 1) {
                thumbs = '<div class="gallery-thumbs">' + images.map(function (img, i) {
                    return '<img src="' + imageUrl(img) + '" class="' + (i === 0 ? 'active' : '') + '" alt="صورة ' + (i + 1) + '">';
                }).join('') + '</div>';
            }
            var phone = place.phone
                ? '<p style="margin-top:10px;">📞 <a href="tel:' + esc(place.phone) + '" style="color:var(--primary);font-weight:700;text-decoration:none;">' + esc(place.phone) + '</a></p>'
                : '';

            var similar = data.places.filter(function (p) {
                return p.category_id === place.category_id && p.id !== place.id;
            }).slice(0, 4);
            var similarHtml = similar.length
                ? '<div class="section-head"><div><h2>أماكن مشابهة</h2></div></div><div class="scroll-row">' +
                  similar.map(function (p) { return placeCard(p, false); }).join('') + '</div>'
                : '';

            byId('page-content').innerHTML =
                '<div class="details-hero"><img id="mainImage" src="' + imageUrl(images[0]) + '" alt="' + esc(place.name) + '">' +
                '<div class="overlay"><div><span class="category ' + esc(place.slug) + '">' + esc(place.icon) + ' ' + esc(place.category) + '</span>' +
                '<h2>' + esc(place.name) + '</h2></div></div></div>' +
                thumbs +
                '<div class="info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 30px 0;">' +
                '<div class="place-details"><h3>📍 الموقع</h3><p>' + esc(place.location) + '</p></div>' +
                '<div class="place-details"><h3>⭐ التقييم</h3><p class="rating">★★★★★ (4.5/5)</p>' + phone + '</div></div>' +
                '<div class="place-details"><h3>🗺️ الموقع على الخريطة</h3><div class="map-embed">' +
                '<iframe src="' + esc(place.maps_embed) + '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="خريطة ' + esc(place.name) + '"></iframe></div>' +
                '<a class="btn" style="margin-top:12px;" href="' + esc(place.maps_url) + '" target="_blank" rel="noopener">🗺️ فتح في خرائط قوقل</a></div>' +
                '<div class="place-details"><h3>📝 الوصف</h3><p>' + esc(place.description).replace(/\r?\n/g, '<br>') + '</p></div>' +
                '<div class="place-details"><h3>✨ المميزات</h3><ul style="list-style: none; padding: 0;">' +
                '<li style="padding: 8px 0;">✓ مكان آمن وموثوق</li><li style="padding: 8px 0;">✓ خدمات سياحية متميزة</li>' +
                '<li style="padding: 8px 0;">✓ إطلالات طبيعية خلابة</li><li style="padding: 8px 0;">✓ مرافق حديثة</li></ul></div>' +
                '<div style="text-align: center; margin: 40px 0;"><a href="contact.html" class="btn">تواصل معنا 📧</a>' +
                '<a href="places.html" class="btn btn-secondary" style="margin-right: 10px;">العودة للأماكن ←</a></div>' +
                similarHtml;

            var main = byId('mainImage');
            document.querySelectorAll('.gallery-thumbs img').forEach(function (thumb) {
                thumb.addEventListener('click', function () {
                    main.src = thumb.src;
                    document.querySelectorAll('.gallery-thumbs img').forEach(function (t) { t.classList.remove('active'); });
                    thumb.classList.add('active');
                });
            });
        }).catch(function () { showLoadError(); });
    }

    // ===================== صفحة التصنيفات =====================
    function initCategories() {
        loadData().then(function (data) {
            byId('categories-grid').innerHTML = data.categories.map(function (c) {
                return '<a class="category-card ' + esc(c.slug) + '" href="places.html?category=' + encodeURIComponent(c.name) + '" style="display:block;text-decoration:none;color:inherit;">' +
                    '<img src="' + imageUrl(c.cover) + '" alt="' + esc(c.name) + '" style="width:100%;height:160px;object-fit:cover;display:block;" loading="lazy">' +
                    '<div class="cc-body"><h3>' + esc(c.icon) + ' ' + esc(c.name) + '</h3><p>' + esc(c.description) + '</p>' +
                    '<p style="margin-top:8px;color:var(--primary);font-weight:600;">' + c.places_count + ' مكان مسجّل</p></div></a>';
            }).join('');
        }).catch(function () { showLoadError(byId('categories-grid')); });
    }

    // ===================== صفحة مناطق ينبع =====================
    function initDestination() {
        var dest = DESTINATIONS[params().get('area')];
        if (!dest) {
            document.title = 'الوجهة غير موجودة - روح ينبع';
            byId('dest-title').textContent = 'الوجهة غير موجودة';
            byId('dest-intro').textContent = 'الرابط المطلوب غير صحيح. اختر إحدى مناطق ينبع من القائمة.';
            byId('page-content').innerHTML = '<a class="btn" href="index.html">العودة للرئيسية</a>';
            return;
        }
        document.title = dest.name + ' - روح ينبع';
        byId('dest-title').textContent = dest.name;
        byId('dest-intro').textContent = dest.intro;
        byId('page-content').innerHTML = '<h2>أفكار لزيارتك</h2><div class="category-grid">' +
            dest.activities.map(function (a) {
                return '<article class="category-card"><div class="cc-body"><h3>' + esc(a[0]) + '</h3><p>' + esc(a[1]) + '</p></div></article>';
            }).join('') + '</div>' +
            '<p>تظهر الأماكن المسجلة التي يرد اسم المنطقة في اسمها أو وصفها أو موقعها.</p>' +
            '<a class="btn" href="places.html?search=' + encodeURIComponent(dest.name) + '">اكتشف أماكن ' + esc(dest.name) + '</a> ' +
            '<a class="btn" href="trip.html">خطط رحلتك</a>';
    }

    // ===================== نموذج التواصل (يُرسل للبريد عبر FormSubmit) =====================
    function initContact() {
        var form = byId('contact-form');
        var status = byId('contact-status');
        var button = form.querySelector('button[type="submit"]');

        function show(type, message) {
            status.className = 'alert ' + (type === 'ok' ? 'alert-success' : 'alert-danger');
            status.textContent = message;
            status.hidden = false;
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            if (form.elements._honey.value) return;
            button.disabled = true;
            button.textContent = 'جارٍ الإرسال...';
            var body = new FormData(form);
            fetch('https://formsubmit.co/ajax/' + CONTACT_EMAIL, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: body
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (json) {
                    if (res.ok && String(json.success) === 'true') {
                        show('ok', 'تم استقبال رسالتك بنجاح! سيتم التواصل معك قريباً.');
                        form.reset();
                    } else {
                        show('error', 'تعذّر إرسال الرسالة حالياً. حاول مرة أخرى أو راسلنا مباشرة على ' + CONTACT_EMAIL);
                    }
                });
            }).catch(function () {
                show('error', 'تعذّر الاتصال. تأكد من اتصالك بالإنترنت أو راسلنا مباشرة على ' + CONTACT_EMAIL);
            }).then(function () {
                button.disabled = false;
                button.textContent = 'إرسال الرسالة 📤';
            });
        });
    }

    var pages = {
        home: initHome,
        places: initPlaces,
        details: initDetails,
        categories: initCategories,
        destination: initDestination,
        contact: initContact
    };

    document.addEventListener('DOMContentLoaded', function () {
        var init = pages[document.body.getAttribute('data-page')];
        if (init) init();
    });
})();
