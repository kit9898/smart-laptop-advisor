import sys

# Read corrupted file
with open('c:/xampp/htdocs/fyp/admin/admin_products.php.corrupted', 'r', encoding='utf-8') as f:
    lines = f.readlines()

# Read complete modal
with open('c:/xampp/htdocs/fyp/admin/add_product_modal_complete.html', 'r', encoding='utf-8') as f:
    modal_lines = f.readlines()

# Read validation JS
with open('c:/xampp/htdocs/f yp/admin/add_product_validation.js', 'r', encoding='utf-8') as f:
    validation_lines = f.readlines()

# Build clean file
# Lines 1-454 are clean content up to the modal
clean_content = ''.join(lines[0:454])

# Add the complete modal (skip first 3 lines which are comments)
modal_content = ''.join(modal_lines[3:])

# Add the rest: Bulk Upload Modal (lines 510-540) + Delete Form (542-546) + Footer + Scripts
rest_content = ''.join(lines[509:548])

# Add script tags and DataTable JS
scripts_start = ''.join(lines[549:663])

# Add validation JS (skip first 2 comment lines)
validation_content = '\n    ' + '    '.join(validation_lines[2:])

# Add closing script tag and HTML
scripts_end = ''.join(lines[1020:1026])

# Combine all parts
final_content = clean_content + '\n' + modal_content + '\n' + rest_content + '\n' + scripts_start + validation_content + scripts_end

# Write clean file
with open('c:/xampp/htdocs/fyp/admin/admin_products.php', 'w', encoding='utf-8', newline='') as f:
    f.write(final_content)

print("✓ Clean file created successfully!")
print(f"Total lines: {len(final_content.splitlines())}")
