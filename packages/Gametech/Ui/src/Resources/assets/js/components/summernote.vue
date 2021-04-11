<template>
    <div>
        <textarea :id="id" :value="value" @change="onChange" :required="required" :name="name" ref="editor" class="editor"></textarea>
    </div>
</template>

<script>
export default {
    name: 'summernote',
    props: {
        required: {
            type: Boolean,
            required: false,
            default: false
        },
        value: {
            type: String
        },
        name: {
            type: String
        },
        placeholder: {
            type: String
        },
        autofocus: {
            type: String
        }
    },
    data() {
        return {
            height: 'auto',
            minHeight: false
        }
    },
    watch: {
        value(val) {
            if (this.summernote.summernote('code') !== val) {
                //this.summernote.summernote("editor.rewind")
                this.summernote.summernote('code', val)
            }
        }
    },
    computed: {
        id() {
            return "sn_" + Date.now()
        }
    },

    mounted() {
        this.summernote = $(this.$refs.editor).summernote({

            height: "auto", minHeight: "200px",
            focus: typeof this.autofocus !== 'undefined',
            placeholder: this.placeholder,
            popover: {
                image: [
                    ['imagesize', ['imageSize100', 'imageSize50', 'imageSize25']],
                    ['float', ['floatLeft', 'floatRight', 'floatNone']],
                    ['remove', ['removeMedia']]
                ]
            }
        })
        this.summernote.on('summernote.change', this.onChange)
        $("#" + this.id).next().find(".note-toolbar").find("[data-toggle=dropdown]").attr('onclick', '$(this).next().toggle()')
            .next().attr('onclick', '$(this).toggle()')
    },
    methods: {
        onChange(we) {
            this.$emit('input', we.target.value)
        },
        onSubmit: function () {
            $(this.$refs.editor).summernote('focus')
        },
    }
};

</script>
