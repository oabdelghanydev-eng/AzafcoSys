/**
 * Export data to CSV file
 */
export function exportToCsv<T extends object>(
    data: T[],
    filename: string,
    columns: { key: keyof T; header: string }[]
): void {
    if (data.length === 0) {
        return;
    }

    // Generate header row
    const headerRow = columns.map(col => `"${col.header}"`).join(',');

    // Generate data rows
    const dataRows = data.map(row => {
        return columns.map(col => {
            const value = row[col.key];
            if (value === null || value === undefined) {
                return '""';
            }
            if (typeof value === 'number') {
                return value.toString();
            }
            // Escape quotes in strings
            return `"${String(value).replace(/"/g, '""')}"`;
        }).join(',');
    });

    // Combine all rows
    const csvContent = [headerRow, ...dataRows].join('\n');

    // Add BOM for Excel compatibility with Arabic/UTF-8
    const bom = '\uFEFF';
    const blob = new Blob([bom + csvContent], { type: 'text/csv;charset=utf-8;' });

    // Create download link
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `${filename}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    // Revoke object URL to free memory
    URL.revokeObjectURL(url);
}

/**
 * Export data to Excel-compatible CSV
 */
export function exportToExcel<T extends object>(
    data: T[],
    filename: string,
    columns: { key: keyof T; header: string }[]
): void {
    // Same as CSV but with .xls extension for auto-open in Excel
    if (data.length === 0) {
        return;
    }

    const headerRow = columns.map(col => `"${col.header}"`).join('\t');
    const dataRows = data.map(row => {
        return columns.map(col => {
            const value = row[col.key];
            if (value === null || value === undefined) {
                return '';
            }
            if (typeof value === 'number') {
                return value.toString();
            }
            return String(value).replace(/"/g, '""');
        }).join('\t');
    });

    const content = [headerRow, ...dataRows].join('\n');
    const bom = '\uFEFF';
    const blob = new Blob([bom + content], { type: 'application/vnd.ms-excel;charset=utf-8;' });

    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `${filename}.xls`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    // Revoke object URL to free memory
    URL.revokeObjectURL(url);
}
