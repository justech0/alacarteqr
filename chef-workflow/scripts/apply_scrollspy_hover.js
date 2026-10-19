const fs = require('fs');

const files = ['index.html', 'chef-onizleme.html', 'public_html/index.html'];

files.forEach(filePath => {
  let content = fs.readFileSync(filePath, 'utf8');

  // 1. Delete old weak is-scroll-active rules (~lines 130-140)
  const oldWeakStart = content.indexOf('/* ── 4. Scroll Active Category Highlight ── */');
  if (oldWeakStart !== -1) {
    const oldWeakEnd = content.indexOf('/* ── 5. Product Card Click Micro-interaction ── */', oldWeakStart);
    if (oldWeakEnd !== -1) {
      content = content.substring(0, oldWeakStart) + content.substring(oldWeakEnd);
    }
  }

  // 2. Set initial n.category to null so all items are rendered for full menu scrolling
  content = content.replace('category:"et",query:""', 'category:null,query:""');

  // 3. Update CSS overrides for category-button hover & is-scroll-active
  const oldHoverStart = content.indexOf('/* ── ELEVATED HOVER EFFECT & REMOVED ACTIVE DOT ── */');
  if (oldHoverStart !== -1) {
    const oldHoverEnd = content.indexOf('/* ── COMPACT FOOTER (NO EXCESS SPACE) ── */', oldHoverStart);
    if (oldHoverEnd !== -1) {
      const newHoverStyles = `/* ── ELEVATED HOVER & SCROLL-ACTIVE HIGHLIGHT ── */
.category-button {
  transition: color .2s ease, transform .22s cubic-bezier(0.2, 0.8, 0.2, 1) !important;
}

.category-button.is-scroll-active {
  transform: translateY(-4px) !important;
  color: var(--green) !important;
}
.category-button.is-scroll-active .category-circle {
  transform: translateY(-5px) scale(1.1) !important;
  outline: 2.5px solid var(--green) !important;
  outline-color: var(--green) !important;
  box-shadow: 0 8px 20px rgba(23, 96, 35, 0.22) !important;
}

@media (hover: hover) {
  .category-button:hover {
    transform: translateY(-4px) !important;
    color: var(--green) !important;
  }
  .category-button:hover .category-circle {
    transform: translateY(-5px) scale(1.1) !important;
    outline: 2.5px solid var(--green) !important;
    outline-color: var(--green) !important;
    box-shadow: 0 8px 20px rgba(23, 96, 35, 0.22) !important;
  }
}

.category-button[aria-pressed=true]:after,
.category-button.is-scroll-active:after {
  display: none !important;
  content: none !important;
}

/* Dark Mode Scroll-Active & Hover */
html[data-theme=dark] .category-button.is-scroll-active {
  color: var(--green) !important;
}
html[data-theme=dark] .category-button.is-scroll-active .category-circle {
  outline: 2.5px solid var(--green) !important;
  outline-color: var(--green) !important;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.45), 0 0 14px rgba(91, 196, 108, 0.25) !important;
}

@media (hover: hover) {
  html[data-theme=dark] .category-button:hover {
    color: var(--green) !important;
  }
  html[data-theme=dark] .category-button:hover .category-circle {
    outline: 2.5px solid var(--green) !important;
    outline-color: var(--green) !important;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.45), 0 0 14px rgba(91, 196, 108, 0.25) !important;
  }
}

/* Reduced Motion */
@media (prefers-reduced-motion: reduce) {
  .category-button,
  .category-button.is-scroll-active,
  .category-button:hover,
  .category-circle,
  .category-button.is-scroll-active .category-circle,
  .category-button:hover .category-circle {
    transform: none !important;
    animation: none !important;
  }
}

`;
      content = content.substring(0, oldHoverStart) + newHoverStyles + content.substring(oldHoverEnd);
    }
  }

  // 4. Update switchCategory function and updateScrollCategory function
  // Replace old switchCategory
  const oldSwitchRegex = /function switchCategory\(catId\)\s*\{[\s\S]*?H\(!0\);\s*\}/;
  const newSwitchCode = `let isScrollSpyLocked = false;
let scrollSpyUnlockTimer = null;
let lastScrollCat = null;

function switchCategory(catId) {
  if (n.query.trim()) {
    n.query = "";
    let sInput = a("#search");
    if (sInput) sInput.value = "";
    H();
  }

  isScrollSpyLocked = true;
  clearTimeout(scrollSpyUnlockTimer);

  lastScrollCat = catId;
  P(".category-button").forEach(btn => {
    let isMatch = btn.dataset.category === catId;
    btn.classList.toggle("is-scroll-active", isMatch);
    if (isMatch) {
      btn.classList.remove("is-just-activated");
      void btn.offsetWidth;
      btn.classList.add("is-just-activated");
      setTimeout(() => btn.classList.remove("is-just-activated"), 350);
      btn.scrollIntoView({
        behavior: B.matches ? "auto" : "smooth",
        inline: "center",
        block: "nearest"
      });
    }
  });

  let targetCard = a('.product-card[data-category="' + catId + '"]');
  if (targetCard) {
    let headerH = a("#header")?.offsetHeight || 85;
    let navH = a(".category-nav")?.offsetHeight || 108;
    let offsetTop = targetCard.getBoundingClientRect().top + window.scrollY - (headerH + navH + 12);
    window.scrollTo({
      top: Math.max(0, offsetTop),
      behavior: B.matches ? "auto" : "smooth"
    });
  }

  const unlock = () => {
    isScrollSpyLocked = false;
    window.removeEventListener("scrollend", unlock);
  };
  if ("onscrollend" in window) {
    window.addEventListener("scrollend", unlock, { once: true });
  }
  scrollSpyUnlockTimer = setTimeout(unlock, 750);
}`;
  content = content.replace(oldSwitchRegex, newSwitchCode);

  // Replace old updateScrollCategory
  const oldUpdateRegex = /function updateScrollCategory\(\)\s*\{[\s\S]*?P\("\.category-button"\)\.forEach\(btn => \{[\s\S]*?\}\);\s*\}/;
  const newUpdateCode = `function updateScrollCategory() {
  if (isScrollSpyLocked) return;
  if (n.category !== null) return;
  if (n.query.trim()) return;

  let allCards = P(".product-card");
  if (!allCards.length) return;

  let headerH = a("#header")?.offsetHeight || 85;
  let navH = a(".category-nav")?.offsetHeight || 108;
  let triggerY = headerH + navH + 16;

  let catFirstCards = [];
  let seenCats = new Set();
  for (let c of allCards) {
    let cat = c.dataset.category;
    if (cat && !seenCats.has(cat)) {
      seenCats.add(cat);
      catFirstCards.push(c);
    }
  }

  let currentCat = null;
  for (let i = 0; i < catFirstCards.length; i++) {
    let r = catFirstCards[i].getBoundingClientRect();
    if (r.top <= triggerY) {
      currentCat = catFirstCards[i].dataset.category;
    }
  }
  if (!currentCat && catFirstCards.length) {
    currentCat = catFirstCards[0].dataset.category;
  }

  if (currentCat && currentCat !== lastScrollCat) {
    lastScrollCat = currentCat;
    P(".category-button").forEach(btn => {
      let isMatch = btn.dataset.category === currentCat;
      btn.classList.toggle("is-scroll-active", isMatch);
      if (isMatch) {
        btn.classList.remove("is-just-activated");
        void btn.offsetWidth;
        btn.classList.add("is-just-activated");
        setTimeout(() => btn.classList.remove("is-just-activated"), 350);
        btn.scrollIntoView({
          behavior: B.matches ? "auto" : "smooth",
          inline: "center",
          block: "nearest"
        });
      }
    });
  } else if (!lastScrollCat && currentCat) {
    lastScrollCat = currentCat;
    P(".category-button").forEach(btn => {
      let isMatch = btn.dataset.category === currentCat;
      btn.classList.toggle("is-scroll-active", isMatch);
    });
  }
}`;
  content = content.replace(oldUpdateRegex, newUpdateCode);

  // 5. Update touch swipe relative to lastScrollCat
  content = content.replace(
    'let currIdx = allCats.indexOf(n.category);',
    'let currIdx = allCats.indexOf(lastScrollCat || allCats[0]);'
  );

  // Validate script syntax
  const scriptMatch = content.match(/<script>([\s\S]*?)<\/script>/);
  if (scriptMatch) {
    try {
      new Function(scriptMatch[1]);
    } catch (err) {
      console.error('Syntax error in ' + filePath + ':', err);
      process.exit(1);
    }
  }

  fs.writeFileSync(filePath, content, 'utf8');
  console.log('Successfully updated ' + filePath);
});
