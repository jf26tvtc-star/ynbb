// Cloudflare Worker — وسيط لوحة تحكم روح ينبع المفتوحة.
// يحمل مفتاح GitHub كسرّ (Secret) باسم GITHUB_TOKEN، ويحفظ تعديلات اللوحة في المستودع.
// لا يقبل إلا: ملف بيانات الموقع data/site.json بعد التحقق من صحته، وصور JPG حقيقية في مجلد images.
// لا يمكن عبره رفع صفحات أو أكواد أو أي ملفات أخرى.

const OWNER = 'jf26tvtc-star';
const REPO = 'ynbb';
const BRANCH = 'main';
const DATA_PATH = 'data/site.json';
const API = `https://api.github.com/repos/${OWNER}/${REPO}`;
const SITE_ORIGIN = 'https://jf26tvtc-star.github.io';

const NEW_IMAGE = /^place_[a-z0-9]{4,16}_[a-f0-9]{6}\.jpg$/;
const IMAGE_REF = /^[A-Za-z0-9][A-Za-z0-9._-]{0,99}\.(?:jpe?g|png|webp|svg)$/;
const REMOVABLE = /^place_[A-Za-z0-9._-]{1,90}\.(?:jpe?g|png|webp)$/;
const SLUGS = ['sea', 'history', 'nature', 'fun', 'general'];
const MAX_IMAGE_BYTES = 3 * 1024 * 1024;

class HttpError extends Error {
    constructor(status, message) {
        super(message);
        this.status = status;
    }
}

function allowedOrigin(origin) {
    return origin === SITE_ORIGIN || /^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(origin || '');
}

function reply(body, status, origin) {
    const headers = { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store', 'Vary': 'Origin' };
    if (allowedOrigin(origin)) {
        headers['Access-Control-Allow-Origin'] = origin;
        headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS';
        headers['Access-Control-Allow-Headers'] = 'Content-Type';
    }
    return new Response(status === 204 ? null : JSON.stringify(body), { status, headers });
}

async function gh(env, path, options = {}) {
    const res = await fetch(API + path, {
        method: options.method || 'GET',
        headers: {
            'Accept': 'application/vnd.github+json',
            'Authorization': 'Bearer ' + env.GITHUB_TOKEN,
            'X-GitHub-Api-Version': '2022-11-28',
            'User-Agent': 'ynbb-admin-worker',
            ...(options.body ? { 'Content-Type': 'application/json' } : {})
        },
        body: options.body ? JSON.stringify(options.body) : undefined
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) {
        const err = new HttpError(500, json.message || 'GitHub HTTP ' + res.status);
        err.github = res.status;
        throw err;
    }
    return json;
}

function base64ToText(b64) {
    const bin = atob(b64.replace(/\s/g, ''));
    const bytes = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    return new TextDecoder('utf-8').decode(bytes);
}

async function readLatest(env) {
    const ref = await gh(env, '/git/ref/heads/' + BRANCH);
    const head = ref.object.sha;
    const file = await gh(env, '/contents/' + DATA_PATH + '?ref=' + head);
    const text = file.content && file.encoding === 'base64'
        ? base64ToText(file.content)
        : base64ToText((await gh(env, '/git/blobs/' + file.sha)).content);
    return { head, data: JSON.parse(text) };
}

// ===================== التحقق من البيانات =====================
function str(value, max, { required = false, pattern = null } = {}) {
    if (typeof value !== 'string') throw new HttpError(400, 'بيانات غير صالحة.');
    const v = value.trim();
    if ((required && !v) || v.length > max || (pattern && v && !pattern.test(v))) throw new HttpError(400, 'بيانات غير صالحة.');
    return v;
}

function int(value) {
    if (!Number.isInteger(value) || value < 0 || value > 1e9) throw new HttpError(400, 'بيانات غير صالحة.');
    return value;
}

function httpsUrl(value, prefix) {
    const v = str(value || '', 1000);
    if (v && (!v.startsWith(prefix) || /[\s"'<>]/.test(v))) throw new HttpError(400, 'رابط غير صالح في البيانات.');
    return v;
}

// يبني نسخة نظيفة تحتوي الحقول المعروفة فقط، ويرفض أي قيمة غير متوقعة
function cleanData(d) {
    if (!d || typeof d !== 'object' || !Array.isArray(d.categories) || !Array.isArray(d.places)) {
        throw new HttpError(400, 'بيانات غير صالحة.');
    }
    if (d.categories.length > 200 || d.places.length > 2000) throw new HttpError(400, 'عدد العناصر أكبر من المسموح.');
    const catIds = new Set();
    const categories = d.categories.map(c => {
        const cat = {
            id: int(c.id),
            name: str(c.name, 100, { required: true }),
            description: str(c.description || '', 255),
            type: str(c.type || '', 20),
            slug: str(c.slug || 'general', 20),
            icon: str(c.icon || '', 16),
            places_count: int(c.places_count || 0),
            cover: str(c.cover || '', 100, { pattern: IMAGE_REF })
        };
        if (!SLUGS.includes(cat.slug) || catIds.has(cat.id)) throw new HttpError(400, 'بيانات تصنيف غير صالحة.');
        catIds.add(cat.id);
        return cat;
    });
    const placeIds = new Set();
    const places = d.places.map(p => {
        if (!Array.isArray(p.images) || p.images.length > 10) throw new HttpError(400, 'بيانات صور غير صالحة.');
        const place = {
            id: int(p.id),
            name: str(p.name, 150, { required: true }),
            description: str(p.description, 10000, { required: true }),
            category_id: int(p.category_id),
            category: str(p.category, 100, { required: true }),
            type: str(p.type || '', 20),
            slug: str(p.slug || 'general', 20),
            icon: str(p.icon || '', 16),
            location: str(p.location, 200, { required: true }),
            phone: str(p.phone || '', 30),
            maps_embed: httpsUrl(p.maps_embed, 'https://www.google.com/maps'),
            maps_url: httpsUrl(p.maps_url, 'https://'),
            google_maps_url: httpsUrl(p.google_maps_url, 'https://'),
            created_at: str(p.created_at, 10, { required: true, pattern: /^\d{4}-\d{2}-\d{2}$/ }),
            images: p.images.map(name => str(name, 100, { required: true, pattern: IMAGE_REF }))
        };
        if (!SLUGS.includes(place.slug) || !catIds.has(place.category_id) || placeIds.has(place.id)) {
            throw new HttpError(400, 'بيانات مكان غير صالحة.');
        }
        placeIds.add(place.id);
        return place;
    });
    const imageCount = places.reduce((n, p) => n + p.images.length, 0);
    return {
        generated_at: new Date().toISOString(),
        hero_image: str(d.hero_image || '', 100, { pattern: IMAGE_REF }),
        stats: { places: places.length, categories: categories.length, images: imageCount },
        categories,
        places
    };
}

function checkImage(img) {
    const name = str(img && img.name, 40, { required: true, pattern: NEW_IMAGE });
    if (typeof img.base64 !== 'string' || !/^[A-Za-z0-9+/]+={0,2}$/.test(img.base64)) throw new HttpError(400, 'صورة غير صالحة.');
    if (img.base64.length * 0.75 > MAX_IMAGE_BYTES) throw new HttpError(413, 'الصورة أكبر من المسموح.');
    const head = atob(img.base64.slice(0, 8));
    if (head.charCodeAt(0) !== 0xFF || head.charCodeAt(1) !== 0xD8 || head.charCodeAt(2) !== 0xFF) {
        throw new HttpError(400, 'يُسمح برفع صور JPG فقط.');
    }
    return { name, base64: img.base64 };
}

// ===================== الحفظ =====================
async function save(env, body) {
    if (typeof body.base !== 'string' || !/^[0-9a-f]{40}$/.test(body.base)) throw new HttpError(400, 'طلب غير صالح.');
    const data = cleanData(body.data);
    const images = (Array.isArray(body.images) ? body.images : []).slice(0, 11).map(checkImage);
    if (images.length > 10) throw new HttpError(400, 'الحد الأقصى 10 صور في المرة الواحدة.');
    const remove = (Array.isArray(body.remove) ? body.remove : []).slice(0, 101)
        .map(name => str(name, 100, { required: true, pattern: REMOVABLE }));
    if (remove.length > 100) throw new HttpError(400, 'طلب غير صالح.');
    const message = 'Admin panel: ' + str(body.message || 'Update site content', 120).replace(/[\r\n]+/g, ' ');

    const ref = await gh(env, '/git/ref/heads/' + BRANCH);
    if (ref.object.sha !== body.base) throw new HttpError(409, 'conflict');

    const tree = [{ path: DATA_PATH, mode: '100644', type: 'blob', content: JSON.stringify(data, null, 4) + '\n' }];
    for (const img of images) {
        const blob = await gh(env, '/git/blobs', { method: 'POST', body: { content: img.base64, encoding: 'base64' } });
        tree.push({ path: 'images/' + img.name, mode: '100644', type: 'blob', sha: blob.sha });
    }
    const stillUsed = new Set(data.places.flatMap(p => p.images));
    if (remove.length) {
        const present = new Set((await gh(env, '/contents/images?ref=' + body.base)).map(f => f.name));
        for (const name of remove) {
            if (present.has(name) && !stillUsed.has(name)) tree.push({ path: 'images/' + name, mode: '100644', type: 'blob', sha: null });
        }
    }
    const baseCommit = await gh(env, '/git/commits/' + body.base);
    const newTree = await gh(env, '/git/trees', { method: 'POST', body: { base_tree: baseCommit.tree.sha, tree } });
    const commit = await gh(env, '/git/commits', { method: 'POST', body: { message, tree: newTree.sha, parents: [body.base] } });
    try {
        await gh(env, '/git/refs/heads/' + BRANCH, { method: 'PATCH', body: { sha: commit.sha, force: false } });
    } catch (err) {
        if (err.github === 422 || err.github === 409) throw new HttpError(409, 'conflict');
        throw err;
    }
    return { head: commit.sha };
}

export default {
    async fetch(request, env) {
        const origin = request.headers.get('Origin') || '';
        const url = new URL(request.url);
        if (request.method === 'OPTIONS') return reply({}, allowedOrigin(origin) ? 204 : 403, origin);
        if (!allowedOrigin(origin)) return reply({ error: 'هذه الخدمة تعمل من لوحة تحكم الموقع فقط.' }, 403, origin);
        if (!env.GITHUB_TOKEN) return reply({ error: 'لم يُضبط مفتاح GitHub في إعدادات الخدمة (GITHUB_TOKEN).' }, 500, origin);
        try {
            if (request.method === 'GET' && url.pathname === '/data') {
                return reply(await readLatest(env), 200, origin);
            }
            if (request.method === 'POST' && url.pathname === '/save') {
                const length = Number(request.headers.get('Content-Length') || 0);
                if (length > 45 * 1024 * 1024) throw new HttpError(413, 'حجم الطلب أكبر من المسموح.');
                const body = await request.json().catch(() => null);
                if (!body) throw new HttpError(400, 'طلب غير صالح.');
                return reply(await save(env, body), 200, origin);
            }
            return reply({ error: 'غير موجود.' }, 404, origin);
        } catch (err) {
            const status = err instanceof HttpError ? err.status : 500;
            const message = err.github === 401 || err.github === 403 || err.github === 404
                ? 'مفتاح GitHub في إعدادات الخدمة غير صالح أو لا يملك صلاحية الكتابة على مستودع ynbb.'
                : status === 500 ? 'حدث خطأ في الخدمة. حاول مرة أخرى.' : err.message;
            return reply({ error: message }, status, origin);
        }
    }
};
