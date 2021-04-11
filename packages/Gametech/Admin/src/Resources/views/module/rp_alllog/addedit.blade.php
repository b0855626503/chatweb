@push('scripts')
    <script type="text/javascript">

        (() => {
            window.app = new Vue({
                el: '#app',

                created() {
                    // this.audio = document.getElementById('alertsound');
                    this.autoCnt(false);
                }
            });
        })()
    </script>
@endpush

