(function() {
  let nonce = 'ac7513f947'; // Replace with latest nonce, or fetch dynamically if possible

  // Create floating card if not exists
  let floatingCard = document.getElementById('floatingTicketCard');
  if (!floatingCard) {
    floatingCard = document.createElement('div');
    floatingCard.id = 'floatingTicketCard';
    floatingCard.style.position = 'absolute';
    floatingCard.style.zIndex = '9999';
    floatingCard.style.background = '#fff';
    floatingCard.style.border = '1px solid #ccc';
    floatingCard.style.padding = '10px';
    floatingCard.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.2)';
    floatingCard.style.maxWidth = '400px';
    floatingCard.style.display = 'none';
    document.body.appendChild(floatingCard);
  }

  const cache = {};   // cache ticket details per ticketId

  async function fetchTicketDetails(ticketId) {
    if (cache[ticketId]) {
      return cache[ticketId];
    }

    try {
      const response = await fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: new URLSearchParams({
          action: 'wpsc_get_individual_ticket',
          nonce: nonce,
          ticket_id: ticketId
        })
      });

      // Check if server returned 503 or other error
      if (!response.ok) {
        console.warn(`Fetch failed for ticket ${ticketId} with status ${response.status}`);
        return '<div>Error fetching ticket info.</div>';
      }

      const html = await response.text();
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      const widget = doc.querySelector('.wpsc-it-widget.wpsc-itw-ticket-fields');

      const content = widget ? widget.outerHTML : '<div>No details found.</div>';
      cache[ticketId] = content;
      return content;
    } catch (error) {
      console.error('Fetch error:', error);
      return '<div>Error fetching ticket info.</div>';
    }
  }

  function attachHoverToRows() {
    document.querySelectorAll('tr.wpsc_tl_tr').forEach(row => {
      if (row._hoverAttached) return;
      row._hoverAttached = true;

      let hoverTimeout;
      row.addEventListener('mouseenter', () => {
        clearTimeout(hoverTimeout);
        hoverTimeout = setTimeout(async () => {
          const onclickText = row.getAttribute('onclick');
          const match = onclickText && onclickText.match(/wpsc_tl_handle_click\(.*?,\s*(\d+),/);
          const ticketId = match && match[1];
          if (ticketId) {
            floatingCard.innerHTML = 'Loading...';
            floatingCard.style.display = 'block';
            const content = await fetchTicketDetails(ticketId);
            floatingCard.innerHTML = content;
            const rect = row.getBoundingClientRect();
            floatingCard.style.top = `${rect.bottom + window.scrollY + 5}px`;
            floatingCard.style.left = `${rect.left + window.scrollX}px`;
          }
        }, 1000); // delay
      });

      row.addEventListener('mouseleave', () => {
        clearTimeout(hoverTimeout);
        floatingCard.style.display = 'none';
      });
    });
  }

  const observer = new MutationObserver((mutations) => {
    // Hide the floating card whenever new content is loaded
    floatingCard.style.display = 'none';
    
    // We might want to limit how often this fires
    attachHoverToRows();
  });
  const target = document.querySelector('.wpsc-ticket-list-tbl')?.parentElement || document.body;
  observer.observe(target, { childList: true, subtree: true });
  attachHoverToRows();
})();
