const fs = require('fs');
const path = require('path');

const files = [
  path.join(__dirname, '../../index.html'),
  path.join(__dirname, '../../chef-onizleme.html'),
  path.join(__dirname, '../../public_html/index.html')
];

files.forEach(filePath => {
  if (!fs.existsSync(filePath)) return;
  let content = fs.readFileSync(filePath, 'utf8');

  // 1. Fix user-select in .feedback-fab
  content = content.replace(
    /(\.feedback-fab\s*\{[^}]*?)(\n\s*user-select:\s*none;)/g,
    '$1\n  -webkit-user-select: none;$2'
  );

  // 2. Fix user-select in .product-card
  content = content.replace(
    /(\.product-card\s*\{[^}]*?)(\n\s*user-select:\s*none;)/g,
    '$1\n  -webkit-user-select: none;$2'
  );

  // 3. Fix user-select in .lang-option
  content = content.replace(
    /(\.lang-option\s*\{[^}]*?)(\n\s*user-select:\s*none;)/g,
    '$1\n  -webkit-user-select: none;$2'
  );

  // 4. Order -webkit-backdrop-filter before backdrop-filter
  content = content.replace(
    /backdrop-filter:\s*blur\(16px\);\s*\n\s*-webkit-backdrop-filter:\s*blur\(16px\);/g,
    '-webkit-backdrop-filter: blur(16px);\n  backdrop-filter: blur(16px);'
  );
  content = content.replace(
    /backdrop-filter:\s*blur\(4px\);\s*\n\s*-webkit-backdrop-filter:\s*blur\(4px\);/g,
    '-webkit-backdrop-filter: blur(4px);\n  backdrop-filter: blur(4px);'
  );

  fs.writeFileSync(filePath, content, 'utf8');
  console.log('Fixed linter issues in:', filePath);
});
