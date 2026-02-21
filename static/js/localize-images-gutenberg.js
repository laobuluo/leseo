(function () {
  const { __ } = wp.i18n;
  const { createElement: el } = wp.element;
  const { registerPlugin } = wp.plugins;
  const { PluginMoreMenuItem } = wp.editPost;
  const { select, dispatch } = wp.data;

  function getPostContent() {
    try {
      return select("core/editor").getEditedPostContent() || "";
    } catch (e) {
      return "";
    }
  }

  function setPostContent(html) {
    try {
      dispatch("core/editor").editPost({ content: html });
    } catch (e) {}
  }

  function getPostId() {
    try {
      const editorPost = select("core/editor").getCurrentPost();
      if (editorPost && editorPost.id) {
        return String(editorPost.id);
      }
    } catch (e) {}
    const elId = document.getElementById("post_ID");
    return elId ? elId.value : "0";
  }

  async function runLocalize() {
    const content = getPostContent();
    if (!content) {
      window.alert(__("正文为空，无需本地化。", "LeSEO"));
      return;
    }

    if (!window.confirm(__("将本篇文章中的外链图片批量本地化并替换为本站URL，确定继续？", "LeSEO"))) {
      return;
    }

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
      setPostContent(payload.content || content);

      const replaced = payload.replaced || 0;
      const errors = Array.isArray(payload.errors) ? payload.errors : [];
      let msg = sprintf(
        __("本地化完成：已替换 %d 张图片。", "LeSEO"),
        replaced
      );
      if (errors.length) {
        msg += "\n\n" + __("部分失败：", "LeSEO") + "\n- " + errors.slice(0, 10).join("\n- ");
        if (errors.length > 10) {
          msg += "\n... " + sprintf(__("还有 %d 条错误", "LeSEO"), errors.length - 10);
        }
      }
      window.alert(msg);
    } catch (err) {
      window.alert(err && err.message ? err.message : __("本地化失败", "LeSEO"));
    }
  }

  function LocalizeMenuItem() {
    return el(
      PluginMoreMenuItem,
      {
        icon: "images-alt2",
        onClick: runLocalize,
      },
      __("图片本地化", "LeSEO")
    );
  }

  registerPlugin("leseo-localize-images", {
    render: LocalizeMenuItem,
  });
})();

