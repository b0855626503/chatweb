<template>
    <div class="gameblock" v-if="item" :key="item.code" ref="game" @clicked="selectTab">
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
    <div v-else></div>
</template>

<script>
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
            const res = await axios.get(`${this.$root.baseUrl}/member/loadgame/${this.item.code}`);
            this.item = res.data;
            return this.item;

        },
    }
};
</script>

