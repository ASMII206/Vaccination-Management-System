(function () {

    var shell = document.querySelector(".p-shell");
    var burger = document.getElementById("pToggle");
    var overlay = document.getElementById("pOverlay");
    if (shell && burger) {
        burger.addEventListener("click", function () { shell.classList.toggle("open"); });
        if (overlay) overlay.addEventListener("click", function () { shell.classList.remove("open"); });
    }


    var modal = document.getElementById("statusModal");
    if (!modal) return;

    var form = modal.querySelector("form");
    var radios = form.querySelectorAll('input[name="status"]');

    function openModal(btn) {
        form.querySelector('input[name="id"]').value = btn.dataset.id;
        document.getElementById("m-child").textContent = btn.dataset.child;
        document.getElementById("m-details").textContent = btn.dataset.details;
        document.getElementById("remarks").value = btn.dataset.remarks || "";
        radios.forEach(function (r) { r.checked = (r.value === btn.dataset.status); });
        modal.classList.add("open");
        document.body.style.overflow = "hidden";
        form.querySelector('input[type="radio"]').focus();
    }

    function closeModal() {
        modal.classList.remove("open");
        document.body.style.overflow = "";
    }

    document.querySelectorAll("[data-status-open]").forEach(function (btn) {
        btn.addEventListener("click", function () { openModal(btn); });
    });
    modal.querySelectorAll("[data-close]").forEach(function (btn) {
        btn.addEventListener("click", closeModal);
    });
    modal.addEventListener("click", function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") closeModal(); });
})();


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
