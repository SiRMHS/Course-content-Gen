jQuery(document).ready(function ($) {
  var $popupOverlay = $("#desc-gen-popup-overlay");
  var $popupMessage = $("#desc-gen-popup-message");

  function showPopup(message) {
    $popupMessage.text(message);
    $popupOverlay.fadeIn(200);
  }

  $("#desc-gen-popup-close").on("click", function () {
    $popupOverlay.fadeOut(200);
  });

  $("#generate-desc-btn").on("click", function (e) {
    e.preventDefault();
    var $button = $(this);
    var $span = $button.find("span");
    var originalText = "جنریت توضیحات";

    if ($button.hasClass("loading")) return;

    var title = $("#title").val();
    var desc = $("#content").val();
    var json = $("#dornalms_course_json textarea").val();

    if (!title || !json) {
      showPopup("عنوان یا سرفصل‌ها یافت نشد!");
      return;
    }

    $button.addClass("loading").prop("disabled", true);
    $span.text("در حال تولید...");

    $.post(
      descGenData.ajax_url,
      {
        action: "generate_course_desc",
        nonce: descGenData.nonce,
        title: title,
        desc: desc,
        json: json,
      },
      function (res) {
        if (res.success && res.data.desc) {
          var clean = res.data.desc
            .replace(/^```html\s*/i, "")
            .replace(/```\s*$/i, "");
          var html = markdownToHtml(clean);
          showDescPopup(res.data.desc, html);
        } else {
          var errorMessage =
            res.data && res.data.msg ? res.data.msg : "خطا در تولید توضیحات";
          showPopup(errorMessage);
        }
      }
    )
      .fail(function () {
        showPopup("خطا در برقراری ارتباط با سرور.");
      })
      .always(function () {
        $button.removeClass("loading").prop("disabled", false);
        $span.text(originalText);
      });
  });

  function showDescPopup(text, html) {
    $("#desc-gen-popup").remove();
    var popup = $(
      '<div id="desc-gen-popup" style="position:fixed;z-index:9999;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;direction:rtl;">' +
        '<div style="background:#fff;max-width:700px;width:90vw;max-height:80vh;overflow:auto;padding:24px 16px 16px 16px;border-radius:8px;box-shadow:0 2px 16px #0002;position:relative;">' +
        '<div style="font-weight:bold;font-size:18px;margin-bottom:12px;">پیش‌نمایش توضیحات تولید شده</div>' +
        '<div style="font-size:15px;line-height:2; margin-bottom:20px;">' +
        html +
        "</div>" +
        '<button id="desc-gen-accept" class="button button-primary" style="margin-left:8px;">تایید و جایگزینی توضیحات</button>' +
        '<button id="desc-gen-cancel" class="button">انصراف</button>' +
        "</div></div>"
    );
    $("body").append(popup);
    $("#desc-gen-accept").on("click", function () {
      var editor =
        typeof tinymce !== "undefined" ? tinymce.get("content") : null;
      if (editor && !editor.isHidden()) {
        editor.setContent(html);
        editor.save();
      } else {
        $("#content").val(html);
      }
      var firstParagraph = "";
      var match = html.match(/<p>(.*?)<\/p>/i);
      if (match && match[1]) {
        firstParagraph = match[1].replace(/<[^>]+>/g, "").trim();
      } else {
        var lines = html.replace(/<[^>]+>/g, "").split(/\n+/);
        for (var i = 0; i < lines.length; i++) {
          if (lines[i].trim()) {
            firstParagraph = lines[i].trim();
            break;
          }
        }
      }
      $("#excerpt").val(firstParagraph);
      $("#desc-gen-popup").remove();
      alert("توضیحات جایگزین شد!");
    });
    $("#desc-gen-cancel").on("click", function () {
      $("#desc-gen-popup").remove();
    });
  }

  function markdownToHtml(md) {
    if (!md) return "";
    let html = md;
    html = html.replace(/^\*\*(.+?)\*\*/gm, "<strong>$1</strong>");
    html = html.replace(/^## (.+)$/gm, "<h2>$1</h2>");
    html = html.replace(/^# (.+)$/gm, "<h1>$1</h1>");
    html = html.replace(/^\* (.+)$/gm, "<li>$1</li>");
    html = html.replace(/\n{2,}/g, "</p><p>");
    html = "<p>" + html + "</p>";
    html = html.replace(/<p><\/p>/g, "");
    html = html.replace(/<p>(<h[12]>)/g, "$1");
    html = html.replace(/(<\/h[12]>)<\/p>/g, "$1");
    html = html.replace(/<p>(<li>)/g, "<ul>$1");
    html = html.replace(/(<\/li>)<\/p>/g, "$1</ul>");
    return html;
  }
});
