/**
 * Main JavaScript file for common functions
 */

// Toggle user dropdown menu
function toggleUserMenu() {
    const dropdown = document.getElementById('userDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('userDropdown');
    const userMenu = document.querySelector('.user-menu');
    if (dropdown && !dropdown.contains(event.target) && !userMenu.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

// Close alert messages
function closeAlert(button) {
    button.parentElement.style.display = 'none';
}

// Confirm delete action
function confirmDelete(message = 'Bạn có chắc chắn muốn xóa?') {
    return confirm(message);
}

// Format currency
function formatCurrency(value) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
    }).format(value);
}

// Validate email
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Validate phone number (Vietnam)
function validatePhoneVN(phone) {
    const re = /^(0|\+84)(9|8|7|5|3)\d{8}$/;
    return re.test(phone);
}

// Show loading indicator
function showLoading() {
    const loader = document.createElement('div');
    loader.className = 'loader';
    loader.innerHTML = '<div class="spinner"></div>';
    document.body.appendChild(loader);
}

// Hide loading indicator
function hideLoading() {
    const loader = document.querySelector('.loader');
    if (loader) {
        loader.remove();
    }
}

// Format date to Vietnamese format
function formatDateVN(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('vi-VN');
}

// Get URL parameter
function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    const results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}

// Export table to CSV
function exportTableToCSV(filename) {
    const csv = [];
    const rows = document.querySelectorAll('table tr');

    rows.forEach(function(row) {
        const cols = row.querySelectorAll('td, th');
        const csvRow = [];
        cols.forEach(function(col) {
            csvRow.push(col.innerText);
        });
        csv.push(csvRow.join(','));
    });

    downloadCSV(csv.join('\n'), filename);
}

// Download CSV file
function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    downloadLink.href = URL.createObjectURL(csvFile);
    downloadLink.download = filename;
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Print page
function printPage(elementId) {
    const printContent = document.getElementById(elementId).innerHTML;
    const printWindow = window.open('', '', 'height=400,width=800');
    printWindow.document.write('<html><head><title>In tài liệu</title>');
    printWindow.document.write('<link rel="stylesheet" href="/quan_ly_vat_tu/assets/css/style.css">');
    printWindow.document.write('</head><body>');
    printWindow.document.write(printContent);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}

// Debounce function for search
function debounce(func, delay) {
    let debounceTimer;
    return function() {
        const context = this;
        const args = arguments;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => func.apply(context, args), delay);
    };
}

// Search in table
function searchTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    const rows = table.querySelectorAll('tbody tr');

    const filter = input.value.toUpperCase();
    rows.forEach(row => {
        const text = row.textContent.toUpperCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}

// Initialize datepicker (requires external library)
function initDatePicker(selector) {
    const elements = document.querySelectorAll(selector);
    elements.forEach(element => {
        element.type = 'date';
    });
}
