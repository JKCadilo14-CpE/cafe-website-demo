(function () {
  Array.prototype.slice.call(document.querySelectorAll("[data-file-label]")).forEach(function (fileLabel) {
    var fileDrop = fileLabel.closest(".settings-file-drop");
    var fileInput = fileDrop ? fileDrop.querySelector('input[type="file"]') : null;
    var defaultLabel = fileLabel.getAttribute("data-default-label") || fileLabel.textContent || "No file selected";

    if (!fileInput) {
      return;
    }

    fileInput.addEventListener("change", function () {
      var fileName = fileInput.files && fileInput.files.length > 0 ? fileInput.files[0].name : defaultLabel;
      fileLabel.textContent = fileName;
      fileLabel.classList.toggle("has-file", fileName !== defaultLabel);
    });
  });

  var settingsNav = document.querySelector("[data-settings-nav]");
  var settingsNavLinks = Array.prototype.slice.call(document.querySelectorAll("[data-settings-nav-link]"));
  var settingsSections = Array.prototype.slice.call(document.querySelectorAll("[data-settings-section]"));

  function setActiveSettingsSection(sectionId) {
    if (!settingsNav || !sectionId) {
      return;
    }

    settingsNavLinks.forEach(function (link) {
      var isActive = link.getAttribute("href") === "#" + sectionId;
      link.classList.toggle("is-active", isActive);
      link.setAttribute("aria-current", isActive ? "true" : "false");
    });
  }

  if (settingsNav && settingsSections.length > 0 && settingsNavLinks.length > 0) {
    settingsNavLinks.forEach(function (link) {
      link.addEventListener("click", function () {
        var targetId = (link.getAttribute("href") || "").replace("#", "");
        setActiveSettingsSection(targetId);
      });
    });

    if ("IntersectionObserver" in window) {
      var settingsObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            setActiveSettingsSection(entry.target.id);
          }
        });
      }, {
        rootMargin: "-30% 0px -55% 0px",
        threshold: 0.01
      });

      settingsSections.forEach(function (section) {
        settingsObserver.observe(section);
      });
    }
  }
})();
