document.oncontextmenu = function() {
    return false
};

document.onselectstart = function() {
    return !(event.srcElement.type !== "text" && event.srcElement.type !== "textarea" && event.srcElement.type !== "password");
};

if (window.sidebar) {
    document.onmousedown = function(e) {
        const obj = e.target;
        return obj.tagName.toUpperCase() === "INPUT" || obj.tagName.toUpperCase() === "TEXTAREA" || obj.tagName.toUpperCase() === "PASSWORD";
    }
}

if (parent.frames.length > 0) top.location.replace(document.location);

