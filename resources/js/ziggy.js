const Ziggy = {
    "url": "http:\/\/sima.local", "port": null, "defaults": {}, "routes": {
        "horizon.stats.index": {"uri": "horizon\/api\/stats", "methods": ["GET", "HEAD"]},
        "horizon.workload.index": {"uri": "horizon\/api\/workload", "methods": ["GET", "HEAD"]},
        "horizon.masters.index": {"uri": "horizon\/api\/masters", "methods": ["GET", "HEAD"]},
        "horizon.monitoring.index": {"uri": "horizon\/api\/monitoring", "methods": ["GET", "HEAD"]},
        "horizon.monitoring.store": {"uri": "horizon\/api\/monitoring", "methods": ["POST"]},
        "horizon.monitoring-tag.paginate": {"uri": "horizon\/api\/monitoring\/{tag}", "methods": ["GET", "HEAD"]},
        "horizon.monitoring-tag.destroy": {
            "uri": "horizon\/api\/monitoring\/{tag}",
            "methods": ["DELETE"],
            "wheres": {"tag": ".*"}
        },
        "horizon.jobs-metrics.index": {"uri": "horizon\/api\/metrics\/jobs", "methods": ["GET", "HEAD"]},
        "horizon.jobs-metrics.show": {"uri": "horizon\/api\/metrics\/jobs\/{id}", "methods": ["GET", "HEAD"]},
        "horizon.queues-metrics.index": {"uri": "horizon\/api\/metrics\/queues", "methods": ["GET", "HEAD"]},
        "horizon.queues-metrics.show": {"uri": "horizon\/api\/metrics\/queues\/{id}", "methods": ["GET", "HEAD"]},
        "horizon.jobs-batches.index": {"uri": "horizon\/api\/batches", "methods": ["GET", "HEAD"]},
        "horizon.jobs-batches.show": {"uri": "horizon\/api\/batches\/{id}", "methods": ["GET", "HEAD"]},
        "horizon.jobs-batches.retry": {"uri": "horizon\/api\/batches\/retry\/{id}", "methods": ["POST"]},
        "horizon.pending-jobs.index": {"uri": "horizon\/api\/jobs\/pending", "methods": ["GET", "HEAD"]},
        "horizon.completed-jobs.index": {"uri": "horizon\/api\/jobs\/completed", "methods": ["GET", "HEAD"]},
        "horizon.silenced-jobs.index": {"uri": "horizon\/api\/jobs\/silenced", "methods": ["GET", "HEAD"]},
        "horizon.failed-jobs.index": {"uri": "horizon\/api\/jobs\/failed", "methods": ["GET", "HEAD"]},
        "horizon.failed-jobs.show": {"uri": "horizon\/api\/jobs\/failed\/{id}", "methods": ["GET", "HEAD"]},
        "horizon.retry-jobs.show": {"uri": "horizon\/api\/jobs\/retry\/{id}", "methods": ["POST"]},
        "horizon.jobs.show": {"uri": "horizon\/api\/jobs\/{id}", "methods": ["GET", "HEAD"]},
        "horizon.index": {"uri": "horizon\/{view?}", "methods": ["GET", "HEAD"], "wheres": {"view": "(.*)"}},
        "sanctum.csrf-cookie": {"uri": "sanctum\/csrf-cookie", "methods": ["GET", "HEAD"]},
        "ignition.healthCheck": {"uri": "_ignition\/health-check", "methods": ["GET", "HEAD"]},
        "ignition.executeSolution": {"uri": "_ignition\/execute-solution", "methods": ["POST"]},
        "ignition.updateConfig": {"uri": "_ignition\/update-config", "methods": ["POST"]},
        "web.user.": {"uri": "\/", "methods": ["GET", "HEAD"], "domain": "sima.local"},
        "web.user.login-page": {"uri": "login", "methods": ["GET", "HEAD"], "domain": "sima.local"},
        "web.user.login": {"uri": "login", "methods": ["POST"], "domain": "sima.local"},
        "web.user.logout": {"uri": "logout", "methods": ["POST"], "domain": "sima.local"},
        "web.user.profile.show": {"uri": "profile", "methods": ["GET", "HEAD"], "domain": "sima.local"},
        "web.user.cartable.inbox.list": {
            "uri": "cartable\/inbox-list",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.cartable.drafted.list": {
            "uri": "cartable\/draft-list",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.cartable.submitted.list": {
            "uri": "cartable\/submit-list",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.cartable.deleted.list": {
            "uri": "cartable\/deleted-list",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.cartable.archived.list": {
            "uri": "cartable\/archived-list",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.cartable.submit.form": {
            "uri": "cartable\/submit-form",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.cartable.submit.action": {
            "uri": "cartable\/submit-action",
            "methods": ["POST"],
            "domain": "sima.local"
        },
        "web.user.cartable.letter.show": {
            "uri": "cartable\/show\/{letter}",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local",
            "bindings": {"letter": "id"}
        },
        "web.user.cartable.sign.action": {
            "uri": "cartable\/sign\/{letter}",
            "methods": ["POST"],
            "domain": "sima.local",
            "bindings": {"letter": "id"}
        },
        "web.user.cartable.refer.action": {
            "uri": "cartable\/refer\/{letter}",
            "methods": ["POST"],
            "domain": "sima.local",
            "bindings": {"letter": "id"}
        },
        "web.user.cartable.reply.action": {
            "uri": "cartable\/reply\/{letter}",
            "methods": ["POST"],
            "domain": "sima.local",
            "bindings": {"letter": "id"}
        },
        "web.user.cartable.download-attachment": {
            "uri": "cartable\/download-attachment\/{letterAttachment}",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local",
            "bindings": {"letterAttachment": "id"}
        },
        "web.user.cartable.draft.action": {"uri": "cartable\/draft", "methods": ["POST"], "domain": "sima.local"},
        "web.user.cartable.drafted.show": {
            "uri": "cartable\/show-draft\/{letter}",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local",
            "bindings": {"letter": "id"}
        },
        "web.user.cartable.drafted.submit": {
            "uri": "cartable\/submit-draft\/{letter}",
            "methods": ["POST"],
            "domain": "sima.local",
            "bindings": {"letter": "id"}
        },
        "web.user.cartable.archive.action": {"uri": "cartable\/archive", "methods": ["POST"], "domain": "sima.local"},
        "web.user.cartable.temp-delete.action": {
            "uri": "cartable\/temp-delete",
            "methods": ["POST"],
            "domain": "sima.local"
        },
        "web.user.cartable.submit.reminder.action": {
            "uri": "cartable\/submit-reminder\/{letter}",
            "methods": ["POST"],
            "domain": "sima.local",
            "bindings": {"letter": "id"}
        },
        "web.user.notification.index": {"uri": "notification", "methods": ["GET", "HEAD"], "domain": "sima.local"},
        "web.user.notification.create.action": {
            "uri": "notification\/create",
            "methods": ["POST"],
            "domain": "sima.local"
        },
        "web.user.department.index": {"uri": "department\/list", "methods": ["GET", "HEAD"], "domain": "sima.local"},
        "web.user.department.create": {"uri": "department\/create", "methods": ["POST"], "domain": "sima.local"},
        "web.user.department.edit": {
            "uri": "department\/edit\/{department}",
            "methods": ["POST"],
            "domain": "sima.local",
            "bindings": {"department": "id"}
        },
        "web.user.department.delete": {
            "uri": "department\/delete\/{department}",
            "methods": ["POST"],
            "domain": "sima.local",
            "bindings": {"department": "id"}
        },
        "web.user.report.users": {"uri": "report\/users", "methods": ["GET", "HEAD"], "domain": "sima.local"},
        "web.user.report.create-excel-users": {
            "uri": "report\/create-excel-users",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.report.total-files": {
            "uri": "report\/total-uploaded-files",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.report.total-files-by-type": {
            "uri": "report\/total-uploaded-files-type",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.report.total-transcribe-files": {
            "uri": "report\/total-uploaded-transcribe-files",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.dashboard.index": {"uri": "dashboard", "methods": ["GET", "HEAD"], "domain": "sima.local"},
        "web.user.dashboard.copy": {"uri": "dashboard\/copy", "methods": ["POST"], "domain": "sima.local"},
        "web.user.dashboard.move": {"uri": "dashboard\/move", "methods": ["POST"], "domain": "sima.local"},
        "web.user.dashboard.permanent-delete": {
            "uri": "dashboard\/delete",
            "methods": ["POST"],
            "domain": "sima.local"
        },
        "web.user.dashboard.trash-list": {
            "uri": "dashboard\/trash",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.dashboard.trash-action": {"uri": "dashboard\/trash", "methods": ["POST"], "domain": "sima.local"},
        "web.user.dashboard.trash-retrieve": {
            "uri": "dashboard\/trash-retrieve",
            "methods": ["POST"],
            "domain": "sima.local"
        },
        "web.user.dashboard.archive-list": {
            "uri": "dashboard\/archive",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.dashboard.archive-action": {"uri": "dashboard\/archive", "methods": ["POST"], "domain": "sima.local"},
        "web.user.dashboard.archive-retrieve": {
            "uri": "dashboard\/archive-retrieve",
            "methods": ["POST"],
            "domain": "sima.local"
        },
        "web.user.dashboard.create-zip": {"uri": "dashboard\/create-zip", "methods": ["POST"], "domain": "sima.local"},
        "web.user.dashboard.search-form": {
            "uri": "dashboard\/search",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.dashboard.search-action": {"uri": "dashboard\/search", "methods": ["POST"], "domain": "sima.local"},
        "web.user.dashboard.folder.show": {
            "uri": "dashboard\/folder\/show\/{folderId?}",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.dashboard.folder.create-root": {
            "uri": "dashboard\/folder\/create-root",
            "methods": ["POST"],
            "domain": "sima.local"
        },
        "web.user.dashboard.folder.create": {
            "uri": "dashboard\/folder\/create\/{folderId?}",
            "methods": ["POST"],
            "domain": "sima.local"
        },
        "web.user.dashboard.folder.rename": {
            "uri": "dashboard\/folder\/rename\/{folderId?}",
            "methods": ["POST"],
            "domain": "sima.local"
        },
        "web.user.dashboard.file.show": {
            "uri": "dashboard\/file\/show\/{fileId?}",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.dashboard.file.add-description": {
            "uri": "dashboard\/file\/add-description\/{fileId?}",
            "methods": ["POST"],
            "domain": "sima.local"
        },
        "web.user.dashboard.file.transcribe": {
            "uri": "dashboard\/file\/transcribe-file\/{fileId?}",
            "methods": ["POST"],
            "domain": "sima.local"
        },
        "web.user.dashboard.file.download.original-file": {
            "uri": "dashboard\/file\/download-original-file\/{fileId?}",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.dashboard.file.download.searchable": {
            "uri": "dashboard\/file\/download-searchable-file\/{fileId?}",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.dashboard.file.download.word": {
            "uri": "dashboard\/file\/download-word-file\/{fileId?}",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.dashboard.file.rename": {
            "uri": "dashboard\/file\/rename\/{fileId?}",
            "methods": ["POST"],
            "domain": "sima.local"
        },
        "web.user.dashboard.file.print.original": {
            "uri": "dashboard\/file\/print\/{fileId?}",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.dashboard.file.upload": {
            "uri": "dashboard\/file\/upload\/{folderId?}",
            "methods": ["POST"],
            "domain": "sima.local"
        },
        "web.user.dashboard.file.create-root": {
            "uri": "dashboard\/file\/upload-root",
            "methods": ["POST"],
            "domain": "sima.local"
        },
        "web.user.dashboard.file.modify-departments": {
            "uri": "dashboard\/file\/modify-departments\/{fileId?}",
            "methods": ["POST"],
            "domain": "sima.local"
        },
        "web.user.user-management.index": {
            "uri": "user-management",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local"
        },
        "web.user.user-management.user-info": {
            "uri": "user-management\/{user}",
            "methods": ["GET", "HEAD"],
            "domain": "sima.local",
            "bindings": {"user": "id"}
        },
        "web.user.user-management.create-user": {
            "uri": "user-management\/create-user",
            "methods": ["POST"],
            "domain": "sima.local"
        },
        "web.user.user-management.delete-user": {
            "uri": "user-management\/delete-user\/{user}",
            "methods": ["POST"],
            "domain": "sima.local",
            "bindings": {"user": "id"}
        },
        "web.user.user-management.edit-user": {
            "uri": "user-management\/edit-user\/{user}",
            "methods": ["POST"],
            "domain": "sima.local",
            "bindings": {"user": "id"}
        },
        "web.user.user-management.search": {
            "uri": "user-management\/search",
            "methods": ["POST"],
            "domain": "sima.local"
        },
        "web.user.api.users": {"uri": "api\/users", "methods": ["POST"], "domain": "sima.local"},
        "web.user.api.letters": {"uri": "api\/letters", "methods": ["POST"], "domain": "sima.local"}
    }
};

if (typeof window !== 'undefined' && typeof window.Ziggy !== 'undefined') {
    Object.assign(Ziggy.routes, window.Ziggy.routes);
}

export {Ziggy};
