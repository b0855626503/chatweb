export default {
    to : function (promise) {
        return promise.then(data => {
            return {
                error: null,
                result: data
            }
        })
            .catch(err => {
                return {
                    error: err
                }
            })
    }
}
