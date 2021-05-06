<template>
    <div class="vld-parent">
        <loading :active.sync="isLoading"
                 :is-full-page="fullPage"
                 :can-cancel="false"
                 :z-index="newindex"
                 :lock-scroll="true"
                 :color="color"
                 :loader="loader"
                 :background-color="bgColor">
            <template #default v-if="useSlot">
                <h3>Loading ... {{ version }}</h3>
            </template>
        </loading>
    </div>
</template>

<script>
import Loading from 'vue-loading-overlay';
import 'vue-loading-overlay/dist/vue-loading.css';

export default {
    props: ['version'],
    data() {
        return {
            isLoading: false,
            fullPage: true,
            useSlot: true,
            color: '#007bff',
            bgColor: '#ffffff',
            newindex: 999999,
        }
    },
    components: {
        Loading
    },
    mounted() {
        this.isLoading = true;

        console.log('mounted');
        // document.addEventListener("readystatechange", () => {
        //     console.log('readystatechange');
        //     this.isLoading = true
        //     this.useSlot = true
        // });
    }

}
</script>
