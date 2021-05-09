<template>
    <div class="gameblock" v-if="item.connect" :key="item.code" ref="game" @clicked="selectTab">
        <img
            loading="lazy"
            :alt="item.name"
            :src="item.image"
            :data-src="item.image"
            :onerror="`this.src='${this.$root.baseUrl}/storage/game_img/default.png'`"
            class="cd-block mx-auto rounded-circle transfer-slide-img lzy_img h-90 w-90">
        <p class="text-main text-center mb-0 cut-text">{{ item.name }}</p>
        <p class="mb-0"></p>
        <p class="text-color-fixed text-center mb-0" v-text="item.balance"></p>
    </div>
    <div style="opacity: 0.1" v-else>
        <img
            loading="lazy"
            :alt="item.name"
            :src="item.image"
            :data-src="item.image"
            :onerror="`this.src='${this.$root.baseUrl}/storage/game_img/default.png'`"
            class="cd-block mx-auto rounded-circle transfer-slide-img lzy_img h-90 w-90">
        <p class="text-main text-center mb-0 cut-text">{{ item.name }}</p>
        <p class="mb-0"></p>
        <p class="text-color-fixed text-center mb-0">0.00</p>
    </div>
</template>

<script>

import to from "../toPromise.js";

export default {
    props: ["item"],
    data() {
        return {
            selectedItem: null
        }
    },
    mounted() {
        this.$emit("changed");
        this.selectedItem = this.$vnode.key;
        this.$nextTick(() => {
            this.loadGameId();
        })
    },
    methods: {
        selectTab(event) {
            // this.
        },
        async loadGameId() {
            let err, res;
            [err, res] = await to(axios.get(`${this.$root.baseUrl}/member/loadgame/${this.item.code}`));
            if (err) {
                return this.item;
            }
            this.item = res.data;
            return this.item;

        },
    }
};
</script>

