(function () {
  var body = document.body;
  var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var siteHeader = document.querySelector("[data-site-header]");
  var nav = document.querySelector("[data-nav]");
  var navToggle = document.querySelector("[data-nav-toggle]");
  var navBackdrop = document.querySelector("[data-nav-backdrop]");
  var navLinks = Array.prototype.slice.call(document.querySelectorAll(".site-nav a"));
  var activeNavLinks = Array.prototype.slice.call(document.querySelectorAll("[data-nav-link]"));

  function normalizePath(path) {
    var filename = String(path || "").split("/").pop();
    return filename || "index.php";
  }

  function setActiveNavLink() {
    var currentPath = normalizePath(window.location.pathname);

    activeNavLinks.forEach(function (link) {
      var linkPath = "";

      try {
        linkPath = normalizePath(new URL(link.getAttribute("href"), window.location.href).pathname);
      } catch (error) {
        linkPath = normalizePath(link.getAttribute("href"));
      }

      var isActive = linkPath === currentPath;

      if (currentPath === "index.php" && (linkPath === "" || linkPath === "index.php")) {
        isActive = true;
      }

      link.classList.toggle("is-active", isActive);

      if (isActive) {
        link.setAttribute("aria-current", "page");
      } else {
        link.removeAttribute("aria-current");
      }
    });
  }

  function updateHeaderState() {
    if (!siteHeader) {
      return;
    }

    siteHeader.classList.toggle("is-scrolled", window.scrollY > 8);
  }

  setActiveNavLink();
  updateHeaderState();

  if (siteHeader) {
    window.addEventListener("scroll", updateHeaderState, { passive: true });
  }

  function setNavOpen(isOpen) {
    if (!nav || !navToggle) {
      return;
    }

    if (navBackdrop) {
      if (isOpen) {
        navBackdrop.hidden = false;
        window.requestAnimationFrame(function () {
          navBackdrop.classList.add("is-visible");
        });
      } else {
        navBackdrop.classList.remove("is-visible");

        window.setTimeout(function () {
          if (!nav.classList.contains("is-open")) {
            navBackdrop.hidden = true;
          }
        }, reduceMotion ? 0 : 220);
      }
    }

    nav.classList.toggle("is-open", isOpen);
    body.classList.toggle("nav-open", isOpen);
    navToggle.setAttribute("aria-expanded", String(isOpen));
    navToggle.setAttribute("aria-label", isOpen ? "Close navigation" : "Open navigation");

    if (!isOpen) {
      setAccountMenuOpen(false);
    }
  }

  if (nav && navToggle) {
    navToggle.addEventListener("click", function () {
      setNavOpen(navToggle.getAttribute("aria-expanded") !== "true");
    });

    navLinks.forEach(function (link) {
      link.addEventListener("click", function () {
        setNavOpen(false);
      });
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        setNavOpen(false);
      }
    });

    document.addEventListener("click", function (event) {
      if (!nav.classList.contains("is-open")) {
        return;
      }

      if (!nav.contains(event.target) && !navToggle.contains(event.target)) {
        setNavOpen(false);
      }
    });

    if (navBackdrop) {
      navBackdrop.addEventListener("click", function () {
        setNavOpen(false);
      });
    }
  }

  var accountMenu = document.querySelector("[data-account-menu]");
  var accountToggle = document.querySelector("[data-account-toggle]");
  var accountPopover = document.querySelector("[data-account-popover]");
  var accountLinks = Array.prototype.slice.call(document.querySelectorAll(".account-popover a"));

  function setAccountMenuOpen(isOpen) {
    if (!accountMenu || !accountToggle || !accountPopover) {
      return;
    }

    accountMenu.classList.toggle("is-open", isOpen);
    accountToggle.setAttribute("aria-expanded", String(isOpen));
    accountToggle.setAttribute("aria-label", isOpen ? "Close account menu" : "Open account menu");
    accountPopover.hidden = !isOpen;
  }

  if (accountMenu && accountToggle && accountPopover) {
    accountToggle.addEventListener("click", function (event) {
      event.stopPropagation();
      setAccountMenuOpen(accountToggle.getAttribute("aria-expanded") !== "true");
    });

    accountLinks.forEach(function (link) {
      link.addEventListener("click", function () {
        setAccountMenuOpen(false);
      });
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        setAccountMenuOpen(false);
      }
    });

    document.addEventListener("click", function (event) {
      if (!accountMenu.contains(event.target)) {
        setAccountMenuOpen(false);
      }
    });
  }

  if (window.matchMedia) {
    var desktopNavQuery = window.matchMedia("(min-width: 861px)");

    function closeMobileOverlaysOnDesktop(event) {
      if (!event.matches) {
        return;
      }

      setNavOpen(false);
      setAccountMenuOpen(false);
    }

    if (typeof desktopNavQuery.addEventListener === "function") {
      desktopNavQuery.addEventListener("change", closeMobileOverlaysOnDesktop);
    } else if (typeof desktopNavQuery.addListener === "function") {
      desktopNavQuery.addListener(closeMobileOverlaysOnDesktop);
    }

    closeMobileOverlaysOnDesktop(desktopNavQuery);
  }

  Array.prototype.slice.call(document.querySelectorAll("[data-password-toggle]")).forEach(function (passwordToggle) {
    passwordToggle.addEventListener("click", function () {
      var passwordControl = passwordToggle.closest(".password-control");
      var passwordInput = passwordControl ? passwordControl.querySelector("[data-password-input]") : null;
      var passwordToggleText = passwordToggle.querySelector("[data-password-toggle-text]");

      if (!passwordInput) {
        return;
      }

      var isPassword = passwordInput.getAttribute("type") === "password";
      passwordInput.setAttribute("type", isPassword ? "text" : "password");
      passwordToggle.setAttribute("aria-label", isPassword ? "Hide password" : "Show password");

      if (passwordToggleText) {
        passwordToggleText.textContent = isPassword ? "Hide" : "Show";
      }
    });
  });

  Array.prototype.slice.call(document.querySelectorAll("[data-input-glow]")).forEach(function (field) {
    field.addEventListener("mousemove", function (event) {
      var rect = field.getBoundingClientRect();
      field.style.setProperty("--glow-x", event.clientX - rect.left + "px");
      field.style.setProperty("--glow-y", event.clientY - rect.top + "px");
    });

    field.addEventListener("mouseenter", function () {
      field.classList.add("is-glowing");
    });

    field.addEventListener("mouseleave", function () {
      field.classList.remove("is-glowing");
    });
  });

  var authPage = document.querySelector("[data-auth-page]");
  var authForms = Array.prototype.slice.call(document.querySelectorAll("[data-auth-form]"));
  var authTransitionLinks = Array.prototype.slice.call(document.querySelectorAll("[data-auth-transition-link]"));

  function setAuthSubmitState(form, isSubmitting) {
    var submitButton = form.querySelector("[data-auth-submit]");
    var submitText = submitButton ? submitButton.querySelector("[data-auth-submit-text]") : null;
    var submitLoading = submitButton ? submitButton.querySelector("[data-auth-submit-loading]") : null;

    form.classList.toggle("is-submitting", isSubmitting);
    form.setAttribute("aria-busy", String(isSubmitting));
    body.classList.toggle("auth-is-leaving", isSubmitting);

    if (authPage) {
      authPage.setAttribute("aria-busy", String(isSubmitting));
    }

    if (submitButton) {
      submitButton.disabled = isSubmitting;
    }

    if (submitText && submitLoading) {
      submitText.hidden = isSubmitting;
      submitLoading.hidden = !isSubmitting;
    }
  }

  authForms.forEach(function (form) {
    form.addEventListener("submit", function (event) {
      if (reduceMotion || form.classList.contains("is-submitting")) {
        return;
      }

      if (typeof form.checkValidity === "function" && !form.checkValidity()) {
        return;
      }

      event.preventDefault();
      setAuthSubmitState(form, true);

      window.setTimeout(function () {
        HTMLFormElement.prototype.submit.call(form);
      }, 220);
    });
  });

  authTransitionLinks.forEach(function (link) {
    link.addEventListener("click", function (event) {
      if (reduceMotion || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
      }

      var href = link.getAttribute("href");

      if (!href || href.charAt(0) === "#") {
        return;
      }

      event.preventDefault();
      body.classList.add("auth-is-leaving");

      window.setTimeout(function () {
        window.location.href = href;
      }, 180);
    });
  });

  var orderTracker = document.querySelector("[data-order-tracker]");

  if (orderTracker) {
    var orderStatusUrl = orderTracker.getAttribute("data-status-url");
    var orderStatusBadge = orderTracker.querySelector("[data-order-status-badge]");
    var orderStatusMessage = orderTracker.querySelector("[data-order-status-message]");
    var orderUpdated = orderTracker.querySelector("[data-order-updated]");
    var orderTotal = orderTracker.querySelector("[data-order-total]");
    var orderProgressPanel = orderTracker.querySelector("[data-order-progress-panel]");
    var orderCancelledNote = orderTracker.querySelector("[data-order-cancelled-note]");
    var orderCancelForm = orderTracker.querySelector("[data-order-cancel-form]");
    var orderCancelButton = orderTracker.querySelector("[data-order-cancel-button]");
    var orderToast = document.querySelector("[data-order-toast]");
    var orderToastMessage = orderToast ? orderToast.querySelector("[data-order-toast-message]") : null;
    var orderToastTimer = null;
    var orderPollTimer = null;
    var knownOrderStatusClasses = ["pending", "preparing", "out-for-delivery", "completed", "cancelled"];
    var knownOrderStateClasses = ["is-complete", "is-current", "is-upcoming"];
    var lastOrderStatus = orderTracker.getAttribute("data-current-status") || "";

    function showOrderToast(message) {
      if (!orderToast || !orderToastMessage) {
        return;
      }

      orderToastMessage.textContent = message;
      orderToast.hidden = false;

      window.requestAnimationFrame(function () {
        orderToast.classList.add("is-visible");
      });

      window.clearTimeout(orderToastTimer);
      orderToastTimer = window.setTimeout(function () {
        orderToast.classList.remove("is-visible");

        window.setTimeout(function () {
          if (!orderToast.classList.contains("is-visible")) {
            orderToast.hidden = true;
          }
        }, reduceMotion ? 0 : 220);
      }, 4200);
    }

    function updateOrderStep(step) {
      var stepElement = orderTracker.querySelector('[data-order-step="' + step.status + '"]');

      if (!stepElement) {
        return;
      }

      knownOrderStatusClasses.forEach(function (className) {
        stepElement.classList.remove(className);
      });

      knownOrderStateClasses.forEach(function (className) {
        stepElement.classList.remove(className);
      });

      stepElement.classList.add(step.class || "pending");
      stepElement.classList.add("is-" + (step.state || "upcoming"));
    }

    function renderOrderStatus(order, shouldAnnounce) {
      if (!order) {
        return;
      }

      var nextStatus = order.status || "Pending";
      var statusChanged = lastOrderStatus !== "" && nextStatus !== lastOrderStatus;

      if (orderStatusBadge) {
        knownOrderStatusClasses.forEach(function (className) {
          orderStatusBadge.classList.remove(className);
        });

        orderStatusBadge.classList.add(order.status_class || "pending");
        orderStatusBadge.textContent = nextStatus;
      }

      if (orderStatusMessage && order.status_message) {
        orderStatusMessage.textContent = order.status_message;
      }

      if (orderUpdated && order.updated_label) {
        orderUpdated.textContent = order.updated_label;
      }

      if (orderTotal && order.total) {
        orderTotal.textContent = order.total;
      }

      if (orderProgressPanel) {
        orderProgressPanel.classList.toggle("is-cancelled", nextStatus === "Cancelled");
      }

      if (orderCancelledNote) {
        orderCancelledNote.hidden = nextStatus !== "Cancelled";

        if (nextStatus === "Cancelled" && order.cancel_reason) {
          var reasonText = orderCancelledNote.querySelector("span");

          if (reasonText) {
            reasonText.textContent = order.cancel_reason;
          }
        }
      }

      if (Array.isArray(order.progress_steps)) {
        order.progress_steps.forEach(updateOrderStep);
      }

      if (orderCancelForm) {
        orderCancelForm.hidden = !order.can_cancel;
      }

      orderTracker.setAttribute("data-current-status", nextStatus);

      if (shouldAnnounce && statusChanged) {
        showOrderToast("Order #" + orderTracker.getAttribute("data-order-id") + " is now " + nextStatus + ".");
      }

      lastOrderStatus = nextStatus;

      if (order.is_terminal && orderPollTimer) {
        window.clearInterval(orderPollTimer);
        orderPollTimer = null;
      }
    }

    async function loadOrderStatus(shouldAnnounce) {
      if (!orderStatusUrl) {
        return;
      }

      var url = new URL(orderStatusUrl, window.location.href);
      url.searchParams.set("_", String(Date.now()));

      var response = await fetch(url.toString(), {
        credentials: "same-origin",
        headers: {
          Accept: "application/json"
        }
      });

      if (!response.ok) {
        return;
      }

      var data = await response.json();
      renderOrderStatus(data.order, shouldAnnounce);
    }

    function startOrderPolling() {
      if (orderPollTimer || !orderStatusUrl) {
        return;
      }

      orderPollTimer = window.setInterval(function () {
        if (!document.hidden) {
          loadOrderStatus(true).catch(function () {});
        }
      }, 12000);
    }

    if (orderCancelForm) {
      orderCancelForm.addEventListener("submit", async function (event) {
        event.preventDefault();

        if (!window.confirm("Cancel this order? This cannot be undone.")) {
          return;
        }

        if (orderCancelButton) {
          orderCancelButton.disabled = true;
          orderCancelButton.textContent = "Cancelling...";
        }

        try {
          var response = await fetch(orderCancelForm.action, {
            method: "POST",
            credentials: "same-origin",
            headers: {
              Accept: "application/json",
              "X-Requested-With": "XMLHttpRequest"
            },
            body: new FormData(orderCancelForm)
          });
          var data = await response.json();

          if (!response.ok || data.error) {
            showOrderToast(data.error || "Unable to cancel this order.");
            return;
          }

          renderOrderStatus(data.order, false);
          showOrderToast(data.message || "Your order has been cancelled.");
        } catch (error) {
          showOrderToast("Unable to cancel this order right now.");
        } finally {
          if (orderCancelButton) {
            orderCancelButton.disabled = false;
            orderCancelButton.textContent = "Cancel order";
          }
        }
      });
    }

    document.addEventListener("visibilitychange", function () {
      if (!document.hidden) {
        loadOrderStatus(true).catch(function () {});
      }
    });

    startOrderPolling();
  }

  var revealItems = Array.prototype.slice.call(document.querySelectorAll("[data-reveal], [data-footer-reveal]"));

  if (revealItems.length > 0) {
    if (reduceMotion || !("IntersectionObserver" in window)) {
      revealItems.forEach(function (item) {
        item.classList.add("is-visible");
      });
    } else {
      body.classList.add("reveal-ready");

      var revealObserver = new IntersectionObserver(function (entries, observer) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) {
            return;
          }

          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        });
      }, {
        rootMargin: "0px 0px -12% 0px",
        threshold: 0.12
      });

      revealItems.forEach(function (item) {
        revealObserver.observe(item);
      });
    }
  }
})();
