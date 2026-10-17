(function () {
    var startView = document.body.dataset.view === 'register' ? 'register' : 'login';
    var total = 3;
    var current = 1;

    var box = document.getElementById('box');
    var headTitle = document.getElementById('headTitle');
    var headText = document.getElementById('headText');
    var loginView = document.getElementById('loginView');
    var registerView = document.getElementById('registerView');
    var openButtons = document.querySelectorAll('[data-open]');
    var toLogin = document.getElementById('toLogin');
    var eyeBtn = document.getElementById('toggle');
    var eyeIcon = document.getElementById('eyeIcon');
    var loginPass = document.getElementById('loginPassword');

    var form = document.getElementById('registerForm');
    var roleInput = document.getElementById('role');
    var nameLabel = document.getElementById('nameLabel');
    var thirdName = document.getElementById('thirdName');
    var password = document.getElementById('password');
    var confirmPass = document.getElementById('confirm_password');
    var backBtn = document.getElementById('backBtn');
    var nextBtn = document.getElementById('nextBtn');
    var submitBtn = document.getElementById('submitBtn');
    var roleButtons = document.querySelectorAll('[data-role]');
    var steps = document.querySelectorAll('.step');
    var dots = document.querySelectorAll('.step-item');
    var parentOnly = document.querySelectorAll('.parent-only');
    var hospitalOnly = document.querySelectorAll('.hospital-only');

    function showView(name) {
        var isLogin = name === 'login';
        loginView.hidden = !isLogin;
        registerView.hidden = isLogin;
        box.classList.toggle('is-login', isLogin);
        headTitle.textContent = isLogin ? 'Welcome Back to VaxCare' : 'Create your account';
        headText.textContent = isLogin
            ? "Sign in to manage your child's vaccinations."
            : 'Choose how you want to join VaxCare.';
    }

    function switchGroup(items, on) {
        items.forEach(function (item) {
            item.hidden = !on;
            var input = item.querySelector('input');
            if (input) {
                input.disabled = !on;
            }
        });
    }

    function showStep(n) {
        current = n;

        steps.forEach(function (step) {
            step.classList.toggle('current', Number(step.dataset.step) === n);
        });

        dots.forEach(function (dot) {
            var num = Number(dot.dataset.dot);
            dot.classList.toggle('active', num === n);
            dot.classList.toggle('done', num < n);
            dot.querySelector('.dot').textContent = num < n ? '\u2713' : num;
        });

        backBtn.hidden = n === 1;
        nextBtn.hidden = n === total;
        submitBtn.hidden = n !== total;
    }

    function setRole(role) {
        roleInput.value = role;
        form.classList.add('show');
        nameLabel.textContent = role === 'parent' ? 'Parent Name' : 'Hospital Name';
        thirdName.textContent = role === 'parent' ? 'Child' : 'Location';

        roleButtons.forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.role === role);
        });

        switchGroup(parentOnly, role === 'parent');
        switchGroup(hospitalOnly, role === 'hospital');
        showStep(1);
    }

    function stepIsValid() {
        if (current === 1) {
            confirmPass.setCustomValidity(
                password.value === confirmPass.value ? '' : 'Passwords do not match.'
            );
        }

        var inputs = document.querySelector('.step.current').querySelectorAll('input');

        for (var i = 0; i < inputs.length; i++) {
            if (!inputs[i].disabled && !inputs[i].checkValidity()) {
                inputs[i].reportValidity();
                return false;
            }
        }
        return true;
    }

    function goNext() {
        if (stepIsValid()) {
            showStep(current + 1);
        }
    }

    roleButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            setRole(btn.dataset.role);
        });
    });

    openButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            showView('register');
            setRole(btn.dataset.open);
        });
    });

    toLogin.addEventListener('click', function () {
        showView('login');
    });

    eyeBtn.addEventListener('click', function () {
        var show = loginPass.type === 'password';
        loginPass.type = show ? 'text' : 'password';
        eyeIcon.classList.toggle('fa-eye-slash', !show);
        eyeIcon.classList.toggle('fa-eye', show);
        eyeBtn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });

    nextBtn.addEventListener('click', goNext);

    backBtn.addEventListener('click', function () {
        showStep(current - 1);
    });

    confirmPass.addEventListener('input', function () {
        confirmPass.setCustomValidity('');
    });

    form.addEventListener('submit', function (e) {
        if (current < total) {
            e.preventDefault();
            goNext();
            return;
        }

        if (!stepIsValid()) {
            e.preventDefault();
        }
    });

    if (roleInput.value === 'parent' || roleInput.value === 'hospital') {
        setRole(roleInput.value);
    }

    showView(startView);
})();