
(function () {
    var role = document.getElementById("role");
    if (!role) return;

    var nameLabel = document.getElementById("nameLabel");
    var locationGroup = document.getElementById("locationGroup");
    var locationInput = document.getElementById("location");
    var parentFields = document.querySelectorAll(".parent-only");

    function setDisabled(container, state) {
        container.querySelectorAll("input").forEach(function (input) {
            input.disabled = state;
        });
    }

    function apply() {
        var isParent = role.value === "parent";
        var isHospital = role.value === "hospital";

        nameLabel.textContent = isHospital ? "Hospital Name" : (isParent ? "Parent Name" : "Name");

        
        locationGroup.classList.toggle("d-none", !isHospital);
        locationInput.disabled = !isHospital;
        locationInput.required = isHospital;

        
        parentFields.forEach(function (box) {
            box.classList.toggle("d-none", !isParent);
            setDisabled(box, !isParent);
        });
    }

    role.addEventListener("change", apply);
    apply();
})();
