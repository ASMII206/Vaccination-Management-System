
var menuToggle = document.getElementById("menuToggle");
var header = document.querySelector("header");

if (menuToggle && header) {
    menuToggle.addEventListener("click", function () {
        header.classList.toggle("nav-open");
    });

    document.querySelectorAll("header nav a").forEach(function (link) {
        link.addEventListener("click", function () {
            header.classList.remove("nav-open");
        });
    });
}
