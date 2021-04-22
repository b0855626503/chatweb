@push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-colorpicker/css/bootstrap-colorpicker.min.css') }}">
    <style>
        .card-title {
            margin-bottom: .75rem !important;
        }
    </style>
@endpush



<configs :formaddedit="{{ json_encode($configs) }}"></configs>

@push('scripts')
    <script src="{{ asset('/vendor/bootstrap-colorpicker/js/bootstrap-colorpicker.min.js') }}"></script>

    <script type="text/x-template" id="configs-template">

        <b-container class="bv-example-row" v-if="show">
            <b-form @submit.stop.prevent="addEditSubmitNew" id="frmaddedit">

                <b-form-row>
                    <b-col>
                        <b-card title="การยืนยันตน">
                            <b-card-text>
                                <b-form-group
                                    id="input-group-verify_open"
                                    label="เลือกเปิด เมื่อต้องการ ตรวจสอบรหัสของลูกค้า:"
                                    label-for="verify_open"
                                    description="เมื่อสมัครสมาชิกเสร็จ จะเข้าระบบไม่ได้ ถ้าเปิดการใช้งาน โดยทีมงานต้องเข้าไปอนุมัติการใช้งานก่อน">

                                    <b-form-select
                                        id="verify_open"
                                        name="verify_open"
                                        v-model="formaddedit.verify_open"
                                        :options="option.verify_open"
                                        size="sm"
                                        required
                                    ></b-form-select>

                                </b-form-group>
                            </b-card-text>
                        </b-card>
                    </b-col>
                </b-form-row>

                <b-form-row>
                    <b-col>
                        <b-card title="แก้ไขข้อมูลติดต่อ">
                            <b-card-text>
                                <b-form-group
                                    id="input-group-lineid"
                                    label="ID Line :"
                                    label-for="lineid"
                                    description="">
                                    <b-form-input
                                        id="lineid"
                                        name="lineid"
                                        v-model="formaddedit.lineid"
                                        type="text"
                                        size="sm"
                                        placeholder="ID Line"
                                        autocomplete="off"


                                    ></b-form-input>
                                </b-form-group>
                                <b-form-group
                                    id="input-group-linelink"
                                    label="Link Line:"
                                    label-for="linelink"
                                    description="">
                                    <b-form-input
                                        id="linelink"
                                        name="linelink"
                                        v-model="formaddedit.linelink"
                                        type="text"
                                        size="sm"
                                        placeholder="Link Line"
                                        autocomplete="off"

                                    ></b-form-input>
                                </b-form-group>
                                <b-form-group
                                    id="input-group-notice"
                                    label="ข้อความแจ้งเตือนหน้าแรก ก่อนเข้าระบบ:"
                                    label-for="notice"
                                    description="">
                                    <b-form-input
                                        id="notice"
                                        name="notice"
                                        v-model="formaddedit.notice"
                                        type="text"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"

                                    ></b-form-input>
                                </b-form-group>
                            </b-card-text>
                        </b-card>
                    </b-col>
                </b-form-row>

                <b-form-row>
                    <b-col>
                        <b-card title="Wallet Setting">
                            <b-card-text>
                                <b-form-group
                                    id="input-group-maxtransfer_time"
                                    label="สูงสุด โยก Wallet เข้า Game :"
                                    label-for="maxtransfer_time"
                                    description="">
                                    <b-form-input
                                        id="maxtransfer_time"
                                        name="maxtransfer_time"
                                        v-model="formaddedit.maxtransfer_time"
                                        type="number"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                    ></b-form-input>
                                </b-form-group>
                                <b-form-group
                                    id="input-group-mintransfer"
                                    label="ขั้นต่ำ โยก Wallet เข้า Game:"
                                    label-for="mintransfer"
                                    description="">
                                    <b-form-input
                                        id="mintransfer"
                                        name="mintransfer"
                                        v-model="formaddedit.mintransfer"
                                        type="number"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                        required
                                    ></b-form-input>
                                </b-form-group>
                                <b-form-group
                                    id="input-group-mintransfer_pro"
                                    label="เมื่อรับโปร โยก Wallet เข้า Game ได้ เมื่อเงินในเกมน้อยกว่า:"
                                    label-for="mintransfer_pro"
                                    description="">
                                    <b-form-input
                                        id="mintransfer_pro"
                                        name="mintransfer_pro"
                                        v-model="formaddedit.mintransfer_pro"
                                        type="number"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                        required
                                    ></b-form-input>
                                </b-form-group>
                                <b-form-group
                                    id="input-group-mintransferback"
                                    label="ขั้นต่ำ โยก Game เข้า Wallet:"
                                    label-for="mintransferback"
                                    description="">
                                    <b-form-input
                                        id="mintransferback"
                                        name="mintransferback"
                                        v-model="formaddedit.mintransferback"
                                        type="number"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                        required
                                    ></b-form-input>
                                </b-form-group>
                                <b-form-group
                                    id="input-group-minwithdraw"
                                    label="ขั้นต่ำ ถอนเงิน :"
                                    label-for="minwithdraw"
                                    description="">
                                    <b-form-input
                                        id="minwithdraw"
                                        name="minwithdraw"
                                        v-model="formaddedit.minwithdraw"
                                        type="number"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                    ></b-form-input>
                                </b-form-group>
                                <b-form-group
                                    id="input-group-maxwithdraw_day"
                                    label="วงเงินถอน / วัน:"
                                    label-for="maxwithdraw_day"
                                    description="">
                                    <b-form-input
                                        id="maxwithdraw_day"
                                        name="maxwithdraw_day"
                                        v-model="formaddedit.maxwithdraw_day"
                                        type="number"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                        required
                                    ></b-form-input>
                                </b-form-group>

                            </b-card-text>
                        </b-card>
                    </b-col>
                </b-form-row>

                <b-form-row>
                    <b-col>
                        <b-card title="Free Credit Setting">
                            <b-card-text>
                                <b-form-group
                                    id="input-group-freecredit_open"
                                    label="เปิดใช้งาน Free Credit:"
                                    label-for="freecredit_open"
                                    description="">

                                    <b-form-select
                                        id="freecredit_open"
                                        name="freecredit_open"
                                        v-model="formaddedit.freecredit_open"
                                        :options="option.freecredit_open"
                                        size="sm"
                                        required
                                    ></b-form-select>

                                </b-form-group>
                                <b-form-group
                                    id="input-group-freecredit_all"
                                    label="สมาชิกทุกคน เปิดใช้งาน Free Credit:"
                                    label-for="freecredit_all"
                                    description="">

                                    <b-form-select
                                        id="freecredit_all"
                                        name="freecredit_all"
                                        v-model="formaddedit.freecredit_all"
                                        :options="option.freecredit_all"
                                        size="sm"
                                        required
                                    ></b-form-select>

                                </b-form-group>
                                <b-form-group
                                    id="input-group-free_mintransfer"
                                    label="ขั้นต่ำโยก Credit เข้า Game :"
                                    label-for="free_mintransfer"
                                    description="">
                                    <b-form-input
                                        id="free_mintransfer"
                                        name="free_mintransfer"
                                        v-model="formaddedit.free_mintransfer"
                                        type="number"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                    ></b-form-input>
                                </b-form-group>
                                <b-form-group
                                    id="input-group-free_mintransferback"
                                    label="ขั้นต่ำโยก Credit ออก Game :"
                                    label-for="free_mintransferback"
                                    description="">
                                    <b-form-input
                                        id="free_mintransferback"
                                        name="free_mintransferback"
                                        v-model="formaddedit.free_mintransferback"
                                        type="number"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                    ></b-form-input>
                                </b-form-group>
                                <b-form-group
                                    id="input-group-free_maxtransfer"
                                    label="สูงสุดในการโยกเข้า / ครั้ง:"
                                    label-for="free_maxtransfer"
                                    description="">
                                    <b-form-input
                                        id="free_maxtransfer"
                                        name="free_maxtransfer"
                                        v-model="formaddedit.free_maxtransfer"
                                        type="number"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                        required
                                    ></b-form-input>
                                </b-form-group>
                                <b-form-group
                                    id="input-group-free_maxout"
                                    label="สูงสุดการโยกออก / ครั้ง:"
                                    label-for="free_maxout"
                                    description="">
                                    <b-form-input
                                        id="free_maxout"
                                        name="free_maxout"
                                        v-model="formaddedit.free_maxout"
                                        type="number"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                        required
                                    ></b-form-input>
                                </b-form-group>
                                <b-form-group
                                    id="input-group-free_minwithdraw"
                                    label="ขั้นต่ำ ถอนเงิน / ครั้ง:"
                                    label-for="free_minwithdraw"
                                    description="">
                                    <b-form-input
                                        id="free_minwithdraw"
                                        name="free_minwithdraw"
                                        v-model="formaddedit.free_minwithdraw"
                                        type="number"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                        required
                                    ></b-form-input>
                                </b-form-group>
                                <b-form-group
                                    id="input-group-free_maxwithdraw"
                                    label="จำกัด ถอนเงินทั้งหมด:"
                                    label-for="free_maxwithdraw"
                                    description="">
                                    <b-form-input
                                        id="free_maxwithdraw"
                                        name="free_maxwithdraw"
                                        v-model="formaddedit.free_maxwithdraw"
                                        type="number"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                        required
                                    ></b-form-input>
                                </b-form-group>
                            </b-card-text>
                        </b-card>
                    </b-col>
                </b-form-row>

                @if(auth()->guard('admin')->user()->superadmin == 'Y')
                    <b-form-row>
                        <b-col>
                            <b-card title="Core Config">
                                <b-card-text>
                                    <b-form-group
                                        id="input-group-multigame_open"
                                        label="รองรับเกมหลายค่าย:"
                                        label-for="multigame_open"
                                        description="เมื่อ On ใช้ระบบสมาชิกรูปแบบ กระเป๋า Wallet">

                                        <b-form-select
                                            id="multigame_open"
                                            name="multigame_open"
                                            v-model="formaddedit.multigame_open"
                                            :options="option.multigame_open"
                                            size="sm"
                                            required
                                        ></b-form-select>
                                    </b-form-group>

                                    <b-form-group
                                        id="input-group-pro_onoff"
                                        label="Promotion:"
                                        label-for="pro_onoff"
                                        description="เมื่อเลือก Off จะไม่มีโปรโมชั่นแสดงที่ หน้า โยกเงิน">

                                        <b-form-select
                                            id="pro_onoff"
                                            name="pro_onoff"
                                            v-model="formaddedit.pro_onoff"
                                            :options="option.pro_onoff"
                                            size="sm"
                                            required
                                        ></b-form-select>
                                    </b-form-group>

                                    <b-form-group
                                        id="input-group-reward_open"
                                        label="เปิดใช้งานเมนู แต้มแลกรางวัล:"
                                        label-for="reward_open"
                                        description="">

                                        <b-form-select
                                            id="reward_open"
                                            name="reward_open"
                                            v-model="formaddedit.reward_open"
                                            :options="option.reward_open"
                                            size="sm"
                                            required
                                        ></b-form-select>
                                    </b-form-group>
                                </b-card-text>
                            </b-card>
                        </b-col>
                    </b-form-row>
                @endif
                <b-form-row>
                    <b-col>
                        <b-card title="Special Config">
                            <b-card-text>
                                <b-form-group
                                    id="input-group-point_open"
                                    label="เปิดใช้งาน Point:"
                                    label-for="point_open"
                                    description="">

                                    <b-form-select
                                        id="point_open"
                                        name="point_open"
                                        v-model="formaddedit.point_open"
                                        :options="option.point_open"
                                        size="sm"
                                        required
                                    ></b-form-select>
                                </b-form-group>
                                <b-form-group
                                    id="input-group-points"
                                    label="เติมทุก xxx / 1 Point :"
                                    label-for="points"
                                    description="">
                                    <b-form-input
                                        id="points"
                                        name="points"
                                        v-model="formaddedit.points"
                                        type="number"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                    ></b-form-input>
                                </b-form-group>

                                <hr>

                                <b-form-group
                                    id="input-group-diamond_open"
                                    label="เปิดใช้งาน Diamond:"
                                    label-for="diamond_open"
                                    description="">

                                    <b-form-select
                                        id="diamond_open"
                                        name="diamond_open"
                                        v-model="formaddedit.diamond_open"
                                        :options="option.diamond_open"
                                        size="sm"
                                        required
                                    ></b-form-select>

                                </b-form-group>

{{--                                <b-form-group--}}
{{--                                    id="input-group-diamond_transfer_in"--}}
{{--                                    label="ได้รับเพชร เมื่อ โยกเงินเข้าเกม:"--}}
{{--                                    label-for="diamond_transfer_in"--}}
{{--                                    description="">--}}

{{--                                    <b-form-select--}}
{{--                                        id="diamond_transfer_in"--}}
{{--                                        name="diamond_transfer_in"--}}
{{--                                        v-model="formaddedit.diamond_transfer_in"--}}
{{--                                        :options="option.diamond_transfer_in"--}}
{{--                                        size="sm"--}}
{{--                                        required--}}
{{--                                    ></b-form-select>--}}

{{--                                </b-form-group>--}}

                                <b-form-group
                                    id="input-group-diamond_per_bill"
                                    label="เปิดใช้งาน Diamond ต่อบิล:"
                                    label-for="diamond_per_bill"
                                    description="">

                                    <b-form-select
                                        id="diamond_per_bill"
                                        name="diamond_per_bill"
                                        v-model="formaddedit.diamond_per_bill"
                                        :options="option.diamond_per_bill"
                                        size="sm"
                                        required
                                        v-on:change="changeType($event)"
                                    ></b-form-select>

                                </b-form-group>

                                <b-form-group
                                    id="input-group-diamonds"
                                    label="เติมทุก xxx / 1 Diamond :"
                                    label-for="diamonds"
                                    description="">
                                    <b-form-input
                                        id="diamonds"
                                        name="diamonds"
                                        v-model="formaddedit.diamonds"
                                        type="number"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                    ></b-form-input>
                                </b-form-group>



                                <b-form-group
                                    id="input-group-diamonds_topup"
                                    label="ยอดเงินเติมต่อบิล :"
                                    label-for="diamonds_topup"
                                    description="จำนวนที่เติม ยอดมากกว่าหรือเท่ากับ">
                                    <b-form-input
                                        id="diamonds_topup"
                                        name="diamonds_topup"
                                        v-model="formaddedit.diamonds_topup"
                                        type="number"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                    ></b-form-input>
                                </b-form-group>

                                <b-form-group
                                    id="input-group-diamonds_amount"
                                    label="ได้รับเพชรจำนวน :"
                                    label-for="diamonds_amount"
                                    description="">
                                    <b-form-input
                                        id="diamonds_amount"
                                        name="diamonds_amount"
                                        v-model="formaddedit.diamonds_amount"
                                        type="number"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                    ></b-form-input>
                                </b-form-group>
                                <hr>
                                <b-form-group
                                    id="input-group-maxspin"
                                    label="ยอดรวมรางวัล วงล้อมหาสนุก (สูงสุด) :"
                                    label-for="maxspin"
                                    description="">
                                    <b-form-input
                                        id="maxspin"
                                        name="maxspin"
                                        v-model="formaddedit.maxspin"
                                        type="number"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                    ></b-form-input>
                                </b-form-group>
                            </b-card-text>
                        </b-card>
                    </b-col>
                </b-form-row>


                <b-form-row>
                    <b-col>
                        <b-card title="Logo And Favicon">
                            <b-card-text>
                                <b-row>
                                    <b-col cols="12" md="6">
                                        <div class="form-group {!! $errors->has('filepic.*') ? 'has-error' : '' !!}">
                                            <label>Logo Image (png)</label>
                                            <image-wrapper
                                                @clear="clearImage($event,'filepic')"
                                                @upload="handleUpload($event,'filepic')"
                                                button-label="เพิ่มรูปภาพ"
                                                :removed="true"
                                                input-name="filepic"
                                                :multiple="false"
                                                :images="formaddedit.filepic"
                                                :imgpath="imgpath"
                                                v-bind:testProp.sync="trigger"></image-wrapper>
                                        </div>
                                    </b-col>
                                    <b-col cols="12" md="6">
                                        <div class="form-group {!! $errors->has('favicon.*') ? 'has-error' : '' !!}">
                                            <label>Favicon Image (png)</label>
                                            <image-wrapper
                                                @clear="clearImage($event,'fileimg')"
                                                @upload="handleUpload($event,'fileimg')"
                                                button-label="เพิ่มรูปภาพ"
                                                :removed="true"
                                                input-name="fileimg"
                                                :multiple="false"
                                                :images="formaddedit.fileimg"
                                                :imgpath="imgpath"
                                                v-bind:testProp.sync="triggernew"></image-wrapper>
                                        </div>
                                    </b-col>
                                </b-row>
                            </b-card-text>
                        </b-card>
                    </b-col>
                </b-form-row>

                <b-form-row>
                    <b-col>
                        <b-card title="Website Wallet Setting">
                            <b-card-text>
                                <b-form-group
                                    id="input-group-sitename"
                                    label="ชื่อเวบไซต์ :"
                                    label-for="sitename"
                                    description="">
                                    <b-form-input
                                        id="sitename"
                                        name="sitename"
                                        v-model="formaddedit.sitename"
                                        type="text"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                        required
                                    ></b-form-input>
                                </b-form-group>
                                <b-form-group
                                    id="input-group-title"
                                    label="Title :"
                                    label-for="title"
                                    description="">
                                    <b-form-input
                                        id="title"
                                        name="title"
                                        v-model="formaddedit.title"
                                        type="text"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                        required
                                    ></b-form-input>
                                </b-form-group>
                                <b-form-group
                                    id="input-group-description"
                                    label="Description :"
                                    label-for="description"
                                    description="">
                                    <b-form-textarea
                                        id="description"
                                        name="description"
                                        v-model="formaddedit.description"
                                        placeholder=""
                                        rows="3"
                                        max-rows="6"
                                    ></b-form-textarea>
                                </b-form-group>


                                <b-form-group
                                    id="input-group-wallet_navbar_color"
                                    label="Wallet Navbar Color :"
                                    label-for="wallet_navbar_color"
                                    description="">
                                    <b-input-group class="my-colorpicker my-colorpicker-navbar">
                                        <b-input-group-append>
                                            <b-form-input
                                                id="wallet_navbar_color"
                                                name="wallet_navbar_color"
                                                v-model="formaddedit.wallet_navbar_color"
                                                type="text"
                                                size="sm"
                                                placeholder=""
                                                autocomplete="off"

                                                required
                                            ></b-form-input>
                                            <b-input-group-text>
                                                <i class="fa fa-square"></i>
                                            </b-input-group-text>
                                        </b-input-group-append>
                                    </b-input-group>


                                </b-form-group>


                                <b-form-group
                                    id="input-group-wallet_footer_color"
                                    label="Wallet Footer Color :"
                                    label-for="wallet_footer_color"
                                    description="">
                                    <b-input-group class="my-colorpicker my-colorpicker-footer">
                                        <b-input-group-append>
                                            <b-form-input
                                                id="wallet_footer_color"
                                                name="wallet_footer_color"
                                                v-model="formaddedit.wallet_footer_color"
                                                type="text"
                                                size="sm"
                                                placeholder=""
                                                autocomplete="off"

                                                required
                                            ></b-form-input>
                                            <b-input-group-text>
                                                <i class="fa fa-square footer"></i>
                                            </b-input-group-text>
                                        </b-input-group-append>
                                    </b-input-group>


                                </b-form-group>

                                <b-form-group
                                    id="input-group-wallet_body_start_color"
                                    label="Wallet Body Start Color :"
                                    label-for="wallet_body_start_color"
                                    description="สีเริ่มต้นของพื้นหลังหลัก ซึ่งจะค่อยๆ ไล่เฉดสีไปอ่อน">
                                    <b-input-group class="my-colorpicker my-colorpicker-body-start">
                                        <b-input-group-append>
                                            <b-form-input
                                                id="wallet_body_start_color"
                                                name="wallet_body_start_color"
                                                v-model="formaddedit.wallet_body_start_color"
                                                type="text"
                                                size="sm"
                                                placeholder=""
                                                autocomplete="off"

                                                required
                                            ></b-form-input>
                                            <b-input-group-text>
                                                <i class="fa fa-square body-start"></i>
                                            </b-input-group-text>
                                        </b-input-group-append>
                                    </b-input-group>


                                </b-form-group>

                                <b-form-group
                                    id="input-group-wallet_body_stop_color"
                                    label="Wallet Body Stop Color :"
                                    label-for="wallet_body_stop_color"
                                    description="สีของพื้นหลัง ที่ไล่เฉดสีมาจาก Body Start">
                                    <b-input-group class="my-colorpicker my-colorpicker-body-stop">
                                        <b-input-group-append>
                                            <b-form-input
                                                id="wallet_body_stop_color"
                                                name="wallet_body_stop_color"
                                                v-model="formaddedit.wallet_body_stop_color"
                                                type="text"
                                                size="sm"
                                                placeholder=""
                                                autocomplete="off"

                                                required
                                            ></b-form-input>
                                            <b-input-group-text>
                                                <i class="fa fa-square body-stop"></i>
                                            </b-input-group-text>
                                        </b-input-group-append>
                                    </b-input-group>
                                </b-form-group>



                                <b-form-group
                                    id="input-group-wallet_footer_active"
                                    label="Wallet Footer Active Color :"
                                    label-for="wallet_footer_active"
                                    description="สีของการ Active Menu ตรง Footer">
                                    <b-input-group class="my-colorpicker my-colorpicker-footer-active">
                                        <b-input-group-append>
                                            <b-form-input
                                                id="wallet_footer_active"
                                                name="wallet_footer_active"
                                                v-model="formaddedit.wallet_footer_active"
                                                type="text"
                                                size="sm"
                                                placeholder=""
                                                autocomplete="off"

                                                required
                                            ></b-form-input>
                                            <b-input-group-text>
                                                <i class="fa fa-square footer-active"></i>
                                            </b-input-group-text>
                                        </b-input-group-append>
                                    </b-input-group>
                                </b-form-group>

                                <b-form-group
                                    id="input-group-wallet_footer_exchange"
                                    label="Wallet Footer Exchage Color :"
                                    label-for="wallet_footer_exchange"
                                    description="สีของ Menu โยกเงิน ตรง Footer">
                                    <b-input-group class="my-colorpicker my-colorpicker-footer-exchange">
                                        <b-input-group-append>
                                            <b-form-input
                                                id="wallet_footer_exchange"
                                                name="wallet_footer_exchange"
                                                v-model="formaddedit.wallet_footer_exchange"
                                                type="text"
                                                size="sm"
                                                placeholder=""
                                                autocomplete="off"

                                                required
                                            ></b-form-input>
                                            <b-input-group-text>
                                                <i class="fa fa-square footer-exchange"></i>
                                            </b-input-group-text>
                                        </b-input-group-append>
                                    </b-input-group>
                                </b-form-group>


                            </b-card-text>
                        </b-card>
                    </b-col>
                </b-form-row>

                <b-form-row>
                    <b-col>
                        <b-card title="Website Kiosk Setting">
                            <b-card-text>

                                <b-form-group
                                    id="input-group-admin_navbar_color"
                                    label="สีของ Navbar (หัวเวบ):"
                                    label-for="admin_navbar_color"
                                    description="">

                                    <b-form-select
                                        id="admin_navbar_color"
                                        name="admin_navbar_color"
                                        v-model="formaddedit.admin_navbar_color"
                                        :options="option.admin_navbar_color"
                                        size="sm"
                                        required
                                    ></b-form-select>

                                </b-form-group>

                                <b-form-group
                                    id="input-group-admin_brand_color"
                                    label="สีของ Brand (ตรงที่มี Logo):"
                                    label-for="admin_brand_color"
                                    description="">

                                    <b-form-select
                                        id="admin_brand_color"
                                        name="admin_brand_color"
                                        v-model="formaddedit.admin_brand_color"
                                        :options="option.admin_brand_color"
                                        size="sm"
                                        required
                                    ></b-form-select>

                                </b-form-group>


                            </b-card-text>
                        </b-card>
                    </b-col>
                </b-form-row>

                <b-button type="submit" variant="primary">บันทึก</b-button>

            </b-form>
        </b-container>
    </script>

    <script type="text/javascript">
        (() => {


            Vue.component('configs', {
                'template': '#configs-template',
                props: {
                    formaddedit: {},

                },
                data() {
                    return {
                        show: false,
                        code: 1,
                        trigger: 0,
                        triggernew: 0,
                        fileupload: '',
                        fileuploadnew: '',
                        option: {
                            multigame_open: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                            pro_onoff: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                            point_open: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                            reward_open: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                            diamond_open: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                            freecredit_open: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                            freecredit_all: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                            diamond_per_bill: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                            diamond_transfer_in: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                            verify_open: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                            admin_navbar_color: [
                                {value: 'navbar-white navbar-light', text: 'สีขาว'},
                                {value: 'navbar-gray-dark', text: 'สีเทาดำ'},
                                {value: 'navbar-dark navbar-primary', text: 'สีฟ้า'},
                                {value: 'navbar-dark navbar-success', text: 'สีเขียว'},
                                {value: 'navbar-dark navbar-info', text: 'สีเขียว2'},
                                {value: 'navbar-dark navbar-indigo', text: 'สีม่วง'},
                                {value: 'navbar-dark navbar-warning', text: 'สีเหลือง'},
                                {value: 'navbar-dark navbar-orange', text: 'สีส้ม'},
                                {value: 'navbar-dark navbar-danger', text: 'สีแดง'},

                            ],
                            admin_brand_color: [
                                {value: 'navbar-gray-dark', text: 'สีเทาดำ'},
                                {value: 'navbar-primary', text: 'สีฟ้า'},
                                {value: 'navbar-success', text: 'สีเขียว'},
                                {value: 'navbar-info', text: 'สีเขียว2'},
                                {value: 'navbar-indigo', text: 'สีม่วง'},
                                {value: 'navbar-warning', text: 'สีเหลือง'},
                                {value: 'navbar-orange', text: 'สีส้ม'},
                                {value: 'navbar-danger', text: 'สีแดง'},
                            ],
                        },
                        imgpath: '/storage/img/',
                        formaddedit: {
                            filepic: '',
                            fileimg: '',
                        }

                    };
                },

                mounted() {

                    this.code = null;
                    this.show = false;
                    this.fileupload = '';
                    this.fileuploadnew = '';

                    this.$nextTick(() => {
                        this.show = true;
                        this.code = 1;

                        setTimeout(() => {
                            this.trigger++;
                            this.formaddedit.filepic = 'logo.png';
                            this.triggernew++;
                            this.formaddedit.fileimg = 'favicon.png';
                            $('.my-colorpicker').colorpicker();

                            $('.my-colorpicker-footer').on('colorpickerChange colorpickerCreate', function (event) {
                                $('.my-colorpicker-footer .fa-square').css('color', event.color.toString());
                            });

                            $('.my-colorpicker-footer-active').on('colorpickerChange colorpickerCreate', function (event) {
                                $('.my-colorpicker-footer-active .fa-square').css('color', event.color.toString());
                            });

                            $('.my-colorpicker-footer-exchange').on('colorpickerChange colorpickerCreate', function (event) {
                                $('.my-colorpicker-footer-exchange .fa-square').css('color', event.color.toString());
                            });


                            $('.my-colorpicker-navbar').on('colorpickerChange colorpickerCreate', function (event) {
                                $('.my-colorpicker-navbar .fa-square').css('color', event.color.toString());
                            });


                            $('.my-colorpicker-body-start').on('colorpickerChange colorpickerCreate', function (event) {
                                $('.my-colorpicker-body-start .fa-square').css('color', event.color.toString());
                            });

                            $('.my-colorpicker-body-stop').on('colorpickerChange colorpickerCreate', function (event) {
                                $('.my-colorpicker-body-stop .fa-square').css('color', event.color.toString());
                            });

                            // console.log(this.formaddedit.diamond_per_bill);
                            this.changeType(this.formaddedit.diamond_per_bill);
                        }, 0);

                        // this.setImage();
                    })

                },
                methods: {
                    changeType(event) {
                        if (event == 'Y') {
                            $('#input-group-diamonds_topup').removeClass('hide');
                            $('#input-group-diamonds_amount').removeClass('hide');
                            $('#diamonds_topup').prop('required', true);
                            $('#diamonds_amount').prop('required', true);
                            $('#input-group-diamonds').addClass('hide');
                        } else if (event == 'N') {
                            $('#input-group-diamonds_topup').addClass('hide');
                            $('#input-group-diamonds_amount').addClass('hide');
                            $('#diamonds_topup').prop('required', false);
                            $('#diamonds_amount').prop('required', false);
                            $('#input-group-diamonds').removeClass('hide');
                        }
                    },
                    setImage() {
                        // this.trigger++;
                        // this.formaddedit.logo;
                    },
                    clearImage(value, method) {
                        if (method === 'filepic') {
                            this.trigger++;
                            this.formaddedit.filepic = '';
                        } else {
                            this.triggernew++;
                            this.formaddedit.fileimg = '';
                        }

                    },
                    handleUpload(value, method) {
                        if (method === 'filepic') {
                            this.fileupload = value;
                        } else {
                            this.fileuploadnew = value;
                        }
                    },
                    addEditSubmitNew(event) {
                        event.preventDefault();
                        let url = "{{ route('admin.'.$menu->currentRoute.'.update') }}/" + this.code;

                        let form = $('#frmaddedit')[0];
                        let formData = new FormData(form);
                        // let formData = new FormData();
                        // const json = JSON.stringify({
                        //     firstname: this.formaddedit.firstname,
                        //     lastname: this.formaddedit.lastname,
                        //     bank_code: this.formaddedit.bank_code,
                        //     user_pass: this.formaddedit.user_pass,
                        //     acc_no: this.formaddedit.acc_no,
                        // });

                        // formData.append('data', formDatamain);
                        formData.delete('filepic');
                        // formData.delete('fileimg[image_1]');
                        formData.delete('fileimg');
                        formData.append('fileupload', this.fileupload);
                        formData.append('fileuploadnew', this.fileuploadnew);

                        const config = {headers: {'Content-Type': `multipart/form-data; boundary=${formData._boundary}`}};

                        axios.post(url, formData, config)
                            .then(response => {
                                if (response.data.success === true) {
                                    this.$bvModal.msgBoxOk(response.data.message, {
                                        title: 'ผลการดำเนินการ',
                                        size: 'sm',
                                        buttonSize: 'sm',
                                        okVariant: 'success',
                                        headerClass: 'p-2 border-bottom-0',
                                        footerClass: 'p-2 border-top-0',
                                        centered: true
                                    });
                                    // window.LaravelDataTables["dataTableBuilder"].draw(false);
                                } else {
                                    $.each(response.data.message, function (index, value) {
                                        document.getElementById(index).classList.add("is-invalid");
                                    });
                                    $('input').on('focus', function (event) {
                                        event.preventDefault();
                                        event.stopPropagation();
                                        var id = $(this).attr('id');
                                        document.getElementById(id).classList.remove("is-invalid");
                                    });
                                }
                            })
                            .catch(errors => {
                                Toast.fire({
                                    icon: 'error',
                                    title: errors.response.data
                                })
                            });

                    }
                }

            })


            window.app = new Vue({
                data: function () {
                    return {
                        loopcnts: 0,
                        announce: '',
                        pushmenu: '',
                        toast: '',
                        withdraw_cnt: 0,
                        played: false
                    }
                },
                created() {
                    const self = this;
                    self.autoCnt(false);
                },
                watch: {
                    withdraw_cnt: function (event) {
                        if (event > 0) {
                            this.ToastPlay();
                        }
                    }
                },
                methods: {

                    autoCnt(draw) {
                        const self = this;
                        this.toast = new Toasty({
                            classname: "toast",
                            transition: "fade",
                            insertBefore: true,
                            duration: 1000,
                            enableSounds: true,
                            autoClose: true,
                            progressBar: true,
                            sounds: {
                                info: "sound/alert.mp3",
                                success: "sound/alert.mp3",
                                warning: "vendor/toasty/dist/sounds/warning/1.mp3",
                                error: "storage/sound/alert.mp3",
                            }
                        });
                        this.loadCnt();

                        setInterval(function () {
                            self.loadCnt();
                            self.loopcnts++;
                            // self.$refs.deposit.loadData();
                        }, 50000);

                    },

                    runMarquee() {
                        this.announce = $('#announce');
                        this.announce.marquee({
                            duration: 20000,
                            startVisible: false
                        });
                    },
                    ToastPlay() {

                        this.toast.error('<span class="text-danger">มีการถอนรายการใหม่</span>');
                    },
                    async loadCnt() {
                        const response = await axios.get("{{ url('loadcnt') }}");
                        document.getElementById('badge_bank_in').textContent = response.data.bank_in_today +' / '+ response.data.bank_in;
                        document.getElementById('badge_bank_out').textContent = response.data.bank_out;
                        document.getElementById('badge_withdraw').textContent = response.data.withdraw;
                        document.getElementById('badge_withdraw_free').textContent = response.data.withdraw_free;
                        document.getElementById('badge_confirm_wallet').textContent = response.data.payment_waiting;
                        if (this.loopcnts == 0) {
                            document.getElementById('announce').textContent = response.data.announce;
                            this.runMarquee();
                        } else {
                            if (response.data.announce_new == 'Y') {
                                this.announce.on('finished', (event) => {
                                    document.getElementById('announce').textContent = response.data.announce;
                                    this.announce.trigger('destroy');
                                    this.announce.off('finished');
                                    this.runMarquee();
                                });

                            }
                        }

                        this.withdraw_cnt = response.data.withdraw;

                    }
                }
            });

        })()
    </script>
@endpush
