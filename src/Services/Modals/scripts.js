;(function($) {
    $(document).on('click', '[data-modal]', function(e){
        e.preventDefault();
        var modalId = $(this).data('modal');
        $.openModal($("#" + modalId));
    });
    $(document).on('click', '.modal-discard', function(e){
        $.closeModal($(this).parents('.modal-content'));
    });

    function hasOpen(context) {
        return !!context.find('.modal-content:not(.hidden)').length;
    }

    $.extend({
        closeModal: function($modal) {
            $modal.addClass('hidden');
            if (!hasOpen($modal.parents('.modal-container'))) {
                $modal.parents('.modal-container').removeClass('modal--open');
            }
        },
        openModal: function($modal) {
            $(".modal-content").addClass("hidden");
            $modal.removeClass("hidden").parents('.modal-container').addClass('modal--open');
        }
    })
})(jQuery);
