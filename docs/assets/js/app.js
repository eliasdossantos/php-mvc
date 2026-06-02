/* app.js — PHP MVC Base Docs */

document.addEventListener("DOMContentLoaded", () => {
  // ── Theme ────────────────────────────────────────────────────────────────
  const themeBtn = document.getElementById("themeBtn");
  const html = document.documentElement;

  const saved = localStorage.getItem("docs-theme") || "dark";
  html.setAttribute("data-theme", saved);
  syncThemeBtn(saved);

  themeBtn?.addEventListener("click", () => {
    const cur = html.getAttribute("data-theme");
    const next = cur === "dark" ? "light" : "dark";
    html.setAttribute("data-theme", next);
    localStorage.setItem("docs-theme", next);
    syncThemeBtn(next);
  });

  function syncThemeBtn(theme) {
    if (!themeBtn) return;
    themeBtn.textContent = theme === "dark" ? "☀ Tema Claro" : "🌙 Tema Escuro";
  }

  // ── Highlight.js ─────────────────────────────────────────────────────────
  if (typeof hljs !== "undefined") hljs.highlightAll();

  // ── Active nav on scroll ─────────────────────────────────────────────────
  const sections = document.querySelectorAll(".doc-section");
  const navLinks = document.querySelectorAll(".nav-link");

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        navLinks.forEach((l) => l.classList.remove("active"));
        const active = document.querySelector(
          `.nav-link[href="#${entry.target.id}"]`,
        );
        if (active) {
          active.classList.add("active");
          active.scrollIntoView({ block: "nearest" });
        }
      });
    },
    { rootMargin: "-18% 0px -72% 0px" },
  );

  sections.forEach((s) => observer.observe(s));

  // ── Smooth scroll nav ────────────────────────────────────────────────────
  navLinks.forEach((link) => {
    link.addEventListener("click", (e) => {
      e.preventDefault();
      const href = link.getAttribute("href");
      const target = href ? document.querySelector(href) : null;
      if (target) target.scrollIntoView({ behavior: "smooth" });
      closeSidebar();
    });
  });

  // ── Mobile menu ──────────────────────────────────────────────────────────
  const sidebar = document.getElementById("sidebar");
  const overlay = document.getElementById("sidebarOverlay");

  function openSidebar() {
    sidebar?.classList.add("open");
    overlay?.classList.add("active");
    document.body.style.overflow = "hidden";
  }
  function closeSidebar() {
    sidebar?.classList.remove("open");
    overlay?.classList.remove("active");
    document.body.style.overflow = "";
  }

  document.getElementById("mobileMenuBtn")?.addEventListener("click", () => {
    sidebar?.classList.contains("open") ? closeSidebar() : openSidebar();
  });

  overlay?.addEventListener("click", closeSidebar);

  // ── Folder tree toggle ───────────────────────────────────────────────────
  document.querySelectorAll(".ftree-group-header").forEach((header) => {
    header.addEventListener("click", () =>
      header.parentElement.classList.toggle("collapsed"),
    );
  });
});

// ── Copy buttons ─────────────────────────────────────────────────────────────
function copyCode(btn) {
  const code = btn.closest(".code-block").querySelector("code");
  if (!code) return;
  navigator.clipboard.writeText(code.innerText).then(() => {
    btn.textContent = "✓ Copiado";
    btn.classList.add("copied");
    setTimeout(() => {
      btn.textContent = "Copiar";
      btn.classList.remove("copied");
    }, 2000);
  });
}
