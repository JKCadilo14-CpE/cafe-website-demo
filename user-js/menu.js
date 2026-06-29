(function () {
  var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var menuGrid = document.querySelector("[data-menu-grid]");
  var menuSearch = document.querySelector("[data-menu-search]");
  var menuSearchForm = document.querySelector("[data-menu-search-form]");
  var menuClear = document.querySelector("[data-menu-clear]");
  var menuFilters = Array.prototype.slice.call(document.querySelectorAll("[data-menu-filters] [data-category]"));
  var menuProducts = Array.prototype.slice.call(document.querySelectorAll("[data-product]"));
  var menuCount = document.querySelector("[data-menu-count]");
  var menuEmpty = document.querySelector("[data-menu-empty]");
  var menuAnimationFrame = 0;
  var activeCategory = "all";

  function normalize(value) {
    return String(value || "").trim().toLowerCase();
  }

  function categorySlugToName(value) {
    var slug = normalize(value).replace(/[^a-z0-9-]/g, "");

    if (!slug) {
      return "all";
    }

    return slug.replace(/-/g, " ");
  }

  function categoryNameToSlug(value) {
    return normalize(value).replace(/\s+/g, "-");
  }

  function productMatches(product, query) {
    var productCategory = normalize(product.getAttribute("data-category"));
    var haystack = [
      product.getAttribute("data-name"),
      productCategory,
      product.getAttribute("data-description")
    ].map(normalize).join(" ");

    var matchesCategory = activeCategory === "all" || productCategory === activeCategory;
    var matchesSearch = !query || haystack.indexOf(query) !== -1;

    return matchesCategory && matchesSearch;
  }

  function updateMenu() {
    if (!menuGrid) {
      return;
    }

    var query = normalize(menuSearch ? menuSearch.value : "");
    var visibleCount = 0;
    var visibleProducts = [];

    menuProducts.forEach(function (product) {
      var isVisible = productMatches(product, query);
      product.hidden = !isVisible;

      if (isVisible) {
        visibleCount += 1;
        visibleProducts.push(product);
      }
    });

    if (menuCount) {
      menuCount.textContent = visibleCount === 1 ? "Showing 1 item" : "Showing " + visibleCount + " items";
    }

    if (menuClear && menuSearch) {
      menuClear.hidden = !query;
    }

    if (menuEmpty) {
      menuEmpty.hidden = visibleCount !== 0;
    }

    if (menuAnimationFrame) {
      window.cancelAnimationFrame(menuAnimationFrame);
      menuAnimationFrame = 0;
    }

    menuProducts.forEach(function (product) {
      product.classList.remove("is-filtered-in");
    });

    if (reduceMotion) {
      return;
    }

    visibleProducts.forEach(function (product, index) {
      product.style.setProperty("--menu-stagger", String(Math.min(index, 8)));
    });

    menuAnimationFrame = window.requestAnimationFrame(function () {
      visibleProducts.forEach(function (product) {
        product.classList.add("is-filtered-in");
      });

      menuAnimationFrame = 0;
    });
  }

  function setActiveCategory(category, shouldSyncUrl) {
    activeCategory = normalize(category) || "all";

    var hasMatchingFilter = false;

    menuFilters.forEach(function (item) {
      var isActive = normalize(item.getAttribute("data-category")) === activeCategory;
      item.classList.toggle("is-active", isActive);
      item.setAttribute("aria-pressed", String(isActive));
      hasMatchingFilter = hasMatchingFilter || isActive;
    });

    if (!hasMatchingFilter) {
      activeCategory = "all";

      menuFilters.forEach(function (item) {
        var isActive = normalize(item.getAttribute("data-category")) === "all";
        item.classList.toggle("is-active", isActive);
        item.setAttribute("aria-pressed", String(isActive));
      });
    }

    if (shouldSyncUrl && window.history && window.URLSearchParams) {
      var params = new URLSearchParams(window.location.search);

      if (activeCategory === "all") {
        params.delete("category");
      } else {
        params.set("category", categoryNameToSlug(activeCategory));
      }

      var query = params.toString();
      var nextUrl = window.location.pathname + (query ? "?" + query : "") + window.location.hash;
      window.history.replaceState({}, "", nextUrl);
    }

    updateMenu();
  }

  if (menuGrid) {
    try {
      var initialCategory = new URLSearchParams(window.location.search).get("category");

      if (initialCategory) {
        activeCategory = categorySlugToName(initialCategory);
      }
    } catch (error) {
      activeCategory = "all";
    }

    menuFilters.forEach(function (filter) {
      filter.addEventListener("click", function () {
        setActiveCategory(filter.getAttribute("data-category"), true);
      });
    });

    if (menuSearch) {
      menuSearch.addEventListener("input", updateMenu);
    }

    if (menuClear && menuSearch) {
      menuClear.addEventListener("click", function () {
        menuSearch.value = "";
        updateMenu();
        menuSearch.focus();
      });
    }

    if (menuSearchForm) {
      menuSearchForm.addEventListener("submit", function (event) {
        event.preventDefault();
      });
    }

    setActiveCategory(activeCategory, false);
  }
})();
