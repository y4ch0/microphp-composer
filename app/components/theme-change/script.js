document.addEventListener("DOMContentLoaded", () => {
    const themeButton = document.getElementById("theme-change");
    const pageLogo = document.getElementById("page-logo");

    if (!themeButton || !pageLogo) {
        return;
    }

    themeButton.addEventListener("click", () => {
        if (document.querySelector("body").getAttribute("data-theme") == "dark") {
            document.querySelector("body").setAttribute("data-theme", "light");
            pageLogo.src = themeButton.dataset.logoLight;
        } else {
            document.querySelector("body").setAttribute("data-theme", "dark");
            pageLogo.src = themeButton.dataset.logoDark;
        }
    });
});
