        </div><!-- .admin-body -->
    </main>

    <!-- Global Admin Toast Notification -->
    <div class="toast-adm" id="adminToast">
        <svg width="20" height="20" fill="none" stroke="#38bdf8" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span id="adminToastMsg">Notification message</span>
    </div>

    <script>
        function showToast(msg, isSuccess = true) {
            const toast = document.getElementById('adminToast');
            const toastMsg = document.getElementById('adminToastMsg');
            if (toast && toastMsg) {
                toastMsg.textContent = msg;
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 3500);
            }
        }

        // Show toast if URL param has message
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('msg')) {
                showToast(urlParams.get('msg'));
            } else if (urlParams.has('error')) {
                showToast(urlParams.get('error'), false);
            }
        });
    </script>
</body>
</html>
