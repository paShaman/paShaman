<footer class="footer7 bg-info text-white">
    <div class="container">
        <div class="row">
            <!-- coluumn -->
            <div class="col-lg-8 align-self-center font-14">
                <div class="p-10">
                    Copyright {{ date('Y') }}. paShaman
                </div>
            </div>
            <!-- coluumn -->
            <!-- coluumn -->
            <div class="col-lg-4">
                <div class="p-10 font-14">
                    <a href="mailto:{{ $email }}" class="white-link dl m-t-10 m-b-10 m-r-10">Email: {{ $email }}</a>
                    <div class="round-social dl">
                        <a href="{{ $linkFB }}" target="_blank"><i class="fa fa-facebook"></i></a>
                        <a href="{{ $linkVK }}" target="_blank"><i class="fa fa-vk"></i></a>
                    </div>
                </div>
            </div>
            <!-- coluumn -->
            <!-- coluumn -->
        </div>
    </div>
</footer>