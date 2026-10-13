
(function () {
    var shell = document.querySelector(".p-shell");
    var burger = document.getElementById("pToggle");
    var overlay = document.getElementById("pOverlay");
    if (!shell || !burger) return;

    burger.addEventListener("click", function () { shell.classList.toggle("open"); });
    if (overlay) overlay.addEventListener("click", function () { shell.classList.remove("open"); });
})();
