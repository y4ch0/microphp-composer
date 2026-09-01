// This script is loaded once and handles all DataTable component instances.

document.addEventListener("DOMContentLoaded", function () {
    // Find all DataTable components on the page.
    const allDataTables = document.querySelectorAll(".data-table-component");

    allDataTables.forEach((component) => {
        const button = component.querySelector(".show-data-btn");
        const container = component.querySelector(".table-container");
        let data = [];

        try {
            data = JSON.parse(component.dataset.componentData || "[]");
        } catch (error) {
            console.error("Invalid DataTable component data:", error);
        }

        button.addEventListener("click", function () {
            // Prevent re-rendering if the table already exists.
            if (container.innerHTML.trim() !== "") {
                container.innerHTML = "";
                button.textContent = component.dataset.buttonText || "Show Data";
                return;
            }

            if (data.length === 0) {
                container.innerHTML = "<p>No data available to display.</p>";
                return;
            }

            // Create table structure.
            const table = document.createElement("table");
            const thead = document.createElement("thead");
            const tbody = document.createElement("tbody");
            const headerRow = document.createElement("tr");

            // Create table headers from the keys of the first object.
            const headers = Object.keys(data[0]);
            headers.forEach((headerText) => {
                const th = document.createElement("th");
                th.textContent = headerText.charAt(0).toUpperCase() + headerText.slice(1);
                headerRow.appendChild(th);
            });
            thead.appendChild(headerRow);

            // Create table rows.
            data.forEach((rowData) => {
                const row = document.createElement("tr");
                headers.forEach((header) => {
                    const cell = document.createElement("td");
                    cell.textContent = rowData[header];
                    row.appendChild(cell);
                });
                tbody.appendChild(row);
            });

            table.appendChild(thead);
            table.appendChild(tbody);

            // Clear the container and append the new table.
            container.innerHTML = "";
            container.appendChild(table);
            button.textContent = "Hide Data";
        });
    });
});
