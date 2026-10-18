
(function () {
    var shell = document.querySelector(".p-shell");
    var burger = document.getElementById("pToggle");
    var overlay = document.getElementById("pOverlay");
    if (!shell || !burger) return;

    burger.addEventListener("click", function () { shell.classList.toggle("open"); });
    if (overlay) overlay.addEventListener("click", function () { shell.classList.remove("open"); });
})();

/* Profile dropdown */
(function () {
    var box = document.getElementById("pUser");
    var btn = document.getElementById("pUserBtn");
    if (!box || !btn) return;

    function setOpen(open) {
        box.classList.toggle("open", open);
        btn.setAttribute("aria-expanded", open ? "true" : "false");
    }
    btn.addEventListener("click", function (e) {
        e.stopPropagation();
        setOpen(!box.classList.contains("open"));
    });
    document.addEventListener("click", function (e) { if (!box.contains(e.target)) setOpen(false); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") setOpen(false); });
})();
