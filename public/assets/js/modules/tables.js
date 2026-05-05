(function () {
  const tables = document.querySelectorAll("[data-datatable]");
  if (!tables.length || typeof window.DataTable === "undefined") return;

  tables.forEach((tableEl) => {
    const emptyText = tableEl.dataset.empty || "No hay registros";
    const pageLength = Number(tableEl.dataset.pageLength || 10);

    new DataTable(tableEl, {
      pageLength,
      language: {
        search: "Buscar:",
        lengthMenu: "Mostrar _MENU_",
        info: "Mostrando _START_ a _END_ de _TOTAL_",
        infoEmpty: "Sin registros",
        zeroRecords: emptyText,
        paginate: {
          first: "Primero",
          last: "Último",
          next: "Siguiente",
          previous: "Anterior",
        },
      },
    });
  });
})();