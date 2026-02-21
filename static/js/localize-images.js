(() => {
  function getEditorContent() {
    try {
      if (window.tinymce && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
        return tinymce.activeEditor.getContent({ format: "raw" });
      }
    } catch (e) {}
    const textarea = document.getElementById("content");
    return textarea ? textarea.value : "";
  }

  function setEditorContent(html) {
    try {
      if (window.tinymce && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
        tinymce.activeEditor.setContent(html);
        return;
      }
    } catch (e) {}
    const textarea = document.getElementById("content");
    if (textarea) textarea.value = html;
  }

  function getPostId() {
    const el = document.getElementById("post_ID");
    return el ? el.value : "0";
  }

  async function localizeImages(btn) {
    const content = getEditorContent();
    if (!content) {
      alert("正文为空，无需本地化。");
      return;
    }

    btn.disabled = true;
    const oldText = btn.textContent;
    btn.textContent = "正在本地化...";

    try {
      const form = new FormData();
      form.append("action", "leseo_localize_images");
      form.append("nonce", (window.LESEO_LOCALIZE && LESEO_LOCALIZE.nonce) || "");
      form.append("content", content);
      form.append("postId", getPostId());

      const resp = await fetch(
        (window.LESEO_LOCALIZE && LESEO_LOCALIZE.ajaxUrl) || window.ajaxurl,
        { method: "POST", body: form, credentials: "same-origin" }
      );
      const data = await resp.json();
      if (!data || !data.success) {
        throw new Error((data && data.data && data.data.message) || "本地化失败");
      }
      const payload = data.data || {};
      setEditorContent(payload.content || content);

      const replaced = payload.replaced || 0;
      const errors = Array.isArray(payload.errors) ? payload.errors : [];
      let msg = `本地化完成：已替换 ${replaced} 张图片。`;
      if (errors.length) {
        msg += `\n\n部分失败：\n- ${errors.slice(0, 10).join("\n- ")}`;
        if (errors.length > 10) msg += `\n... 还有 ${errors.length - 10} 条错误`;
      }
      alert(msg);
    } catch (err) {
      alert(err && err.message ? err.message : "本地化失败");
    } finally {
      btn.disabled = false;
      btn.textContent = oldText;
    }
  }

  document.addEventListener("click", (e) => {
    const btn = e.target && e.target.id === "leseo-localize-images-btn" ? e.target : null;
    if (!btn) return;
    e.preventDefault();
    localizeImages(btn);
  });
})();

