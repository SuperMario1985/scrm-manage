/**
 * Created by Dove on 2017-04-12.
 */
$(function () {
	function getWxUnoinId ($elemt, sort, start) {
		start = typeof(start) == 'undefined' ? '' : start;
		sort  = typeof(sort) == 'undefined' ? '' : sort;
		$.ajax({
			type    : 'GET',
			url     : $elemt.data('action') + '&start=' + start,
			dataType: "JSON",
			success : function (data) {
				start       = data.start;
				var total   = data.total;
				var empty   = data.empty;
				var percent = (total - empty) / total * 100;

				$('#process-bar' + sort).attr('style', 'width: ' + percent.toFixed(2) + '%');
				$('#process-bar' + sort + ' em').html(percent.toFixed(2) + ' %');

				if (empty != 0) {
					getWxUnoinId($elemt, sort, start);
				}
			}
		});
	}

	$("#update-wx-union-id").click(function () {
		var $this = $(this);
		getWxUnoinId($this);
	});

	$("#update-wx-union-id-1").click(function () {
		var $this = $(this);
		getWxUnoinId($this, '-1');
	});

	function rePushWxEvent ($elemt, sort, offset, limit, lastId) {
		offset = typeof (offset) == 'undefined' ? '' : offset;
		limit  = typeof (limit) == 'undefined' ? '' : limit;
		sort   = typeof (sort) == 'undefined' ? 'asc' : sort;
		lastId = typeof (lastId) == 'undefined' ? 0 : lastId;

		$.ajax({
			type    : 'GET',
			url     : $elemt.data('action') + '&offset=' + offset + '&limit=' + limit + '&last_id=' + lastId,
			dataType: "JSON",
			success : function (data) {
				offset      = data.offset;
				limit       = data.limit;
				lastId      = data.last_id;
				var total   = data.total;
				var empty   = data.empty;
				var percent = 100;

				if (total != 0) {
					percent = (total - empty) / total * 100;
				}

				$('#process-bar-' + sort).attr('style', 'width: ' + percent.toFixed(2) + '%');
				$('#process-bar-' + sort + ' em').html(percent.toFixed(2) + ' %');

				if (empty != 0) {
					rePushWxEvent($elemt, sort, offset, limit, lastId);
				}
			}
		});
	}

	$("#update-re-push-wx-event-union-id-asc").click(function () {
		var $this = $(this);
		rePushWxEvent($this, 'asc', 151200, 100, 171629);
	});

	$("#update-re-push-wx-event-union-id-desc").click(function () {
		var $this = $(this);
		rePushWxEvent($this, 'desc');
	});
});
