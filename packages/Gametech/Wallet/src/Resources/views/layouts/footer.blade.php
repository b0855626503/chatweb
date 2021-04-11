<footer class="main-footer ml-0 p-0 mt-5">
    <div class="navigation nav-footer">
        <div class="container">
            <div class="row">
                <div class="col-md-8 offset-md-2">
                    <div class="navigation-nav mt-2">
                        <div class="list-inline-item d-flex align-items-end text-center">
                            <a href="{{ route('customer.home.index') }}" class="{{ $menu->getFrontActive('customer.home.index') }}"><i class="fas fa-home mb-0"></i><br>หน้าแรก</a>
                        </div>
                        <div class="list-inline-item d-flex align-items-end text-center">
                            <a href="{{ route('customer.profile.index') }}" class="{{ $menu->getFrontActive('customer.profile.index') }}"><i class="fas fa-user mb-0"></i><br>บัญชี</a>
                        </div>
                        <div class="list-inline-item d-flex align-items-end text-center">
                            <a class="exchange text-center {{ $menu->getFrontActive('customer.transfer.game.index') }}" href="{{ route('customer.transfer.game.index') }}">
                                <i class="fas fa-usd-circle fa-5x mb-0"></i>
                                <p class="text-center"><br>โยกเงิน</p>
                            </a>
                        </div>
                        <div class="list-inline-item d-flex align-items-end text-center">
                            <a href="{{ route('customer.withdraw.index') }}" class="{{ $menu->getFrontActive('customer.withdraw.index') }}">
                                <i class="fas fa-hand-holding-usd mb-0"></i><br>ถอนเงิน</a>
                        </div>
                        <div class="list-inline-item d-flex align-items-end text-center">
                            <a target="_blank" href="{{ $config->linelink }}">
                                <i class="fas fa-comments mb-0"></i>
                                <br>แชทสด </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
