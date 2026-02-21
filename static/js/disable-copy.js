document.oncontextmenu = function() {
    return false;
};

document.onselectstart = function(e) {
    const obj = e && (e.target || e.srcElement);
    if (!obj) return false;
    var tag = obj.tagName ? obj.tagName.toUpperCase() : '';
    var type = (obj.type || '').toLowerCase();
    return !(tag !== 'TEXTAREA' && (tag !== 'INPUT' || (type !== 'text' && type !== 'password')));
};

if (window.sidebar) {
    document.onmousedown = function(e) {
        const obj = e.target;
        if (!obj) return true;
        var tag = obj.tagName ? obj.tagName.toUpperCase() : '';
        return tag === 'INPUT' || tag === 'TEXTAREA';
    };
}

try {
    if (typeof parent !== 'undefined' && parent !== self && parent.frames && parent.frames.length > 0) {
        top.location.replace(document.location);
    }
} catch (e) {
    /* 跨域iframe无法访问parent，忽略 */
}
