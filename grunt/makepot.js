module.exports = {
	dist: {
		options: {
			cwd            : '',
			domainPath     : '/languages',
			exclude        : ['release/.*'],
			include        : [],
			mainFile       : 'activity-heat-map.php',
			potComments    : '',
			potFilename    : 'activity-heat-map.pot',
			potHeaders     : {
				poedit                 : true,
				'x-poedit-keywordslist': true,
				'report-msgid-bugs-to' : 'https://pascalbirchler.com',
				'last-translator'      : 'Pascal Birchler',
				'language-team'        : 'Pascal Birchler <swissspidy@chat.wordpress.org>',
				'x-poedit-country'     : 'Switzerland'
			},
			processPot     : null,
			type           : 'wp-plugin',
			updateTimestamp: false
		}
	}
}
