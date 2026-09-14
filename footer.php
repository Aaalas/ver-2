  </main>
</div>

<script>
  // Mobile sidebar toggle
  (function() {
    const menuBtn = document.getElementById('menuBtn');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!menuBtn || !sidebar || !backdrop) return;

    function closeSidebar() {
      sidebar.classList.remove('open');
      backdrop.classList.remove('show');
    }
    menuBtn.addEventListener('click', function() {
      sidebar.classList.toggle('open');
      backdrop.classList.toggle('show');
    });
    backdrop.addEventListener('click', closeSidebar);
    sidebar.querySelectorAll('.nav-item').forEach(function(link) {
      link.addEventListener('click', closeSidebar);
    });
  })();
</script>
</body>
</html>
