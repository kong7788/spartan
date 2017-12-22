<?php
//核心中文语言包
return [
    //系统错误提示
    'Undefined variable'                                        => '未定义变量',
    'Undefined index'                                           => '未定义数组索引',
    'Undefined offset'                                          => '未定义数组下标',
    'Parse error'                                               => '语法解析错误',
    'Type error'                                                => '类型错误',
    'Fatal error'                                               => '致命错误',
    'syntax error'                                              => '语法错误',

    // 框架核心错误提示
    'system error'                                              => '系统发生错误',
    'system error, contact the admin.'                          => '系统发生错误，请联系管理员。',
    'template file not exiting'                                 => '模版不存在',
    'cached directory has no write permissions'                 => '缓存目录没有写入权限',
    'template file not allow php'                               => '模版文件不允许使用PHP代码',
    'xml tag error.'                                            => 'XML格式标签错误',
    'not support extension'                                     => '未支持的扩展',

    // 上传错误信息
    'unknown upload error'                                      => '未知上传错误！',
    'file write error'                                          => '文件写入失败！',
    'upload temp dir not found'                                 => '找不到临时文件夹！',
    'no file to uploaded'                                       => '没有文件被上传！',
    'only the portion of file is uploaded'                      => '文件只有部分被上传！',
    'upload File size exceeds the maximum value'                => '上传文件大小超过了最大值！',
    'upload write error'                                        => '文件上传保存错误！',
    'has the same filename: {:filename}'                        => '存在同名文件：{:filename}',
    'upload illegal files'                                      => '非法上传文件',
    'illegal image files'                                       => '非法图片文件',
    'extensions to upload is not allowed'                       => '上传文件后缀不允许',
    'mimetype to upload is not allowed'                         => '上传文件MIME类型不允许！',
    'filesize not match'                                        => '上传文件大小不符！',
    'directory {:path} creation failed'                         => '目录 {:path} 创建失败！',

    // Validate Error Message
    ':attribute require'                                        => ':attribute不能为空',
    ':attribute must be numeric'                                => ':attribute必须是数字',
    ':attribute must be integer'                                => ':attribute必须是整数',
    ':attribute must be float'                                  => ':attribute必须是浮点数',
    ':attribute must be bool'                                   => ':attribute必须是布尔值',
    ':attribute not a valid email address'                      => ':attribute格式不符',
    ':attribute not a valid mobile'                             => ':attribute格式不符',
    ':attribute must be a array'                                => ':attribute必须是数组',
    ':attribute must be yes,on or 1'                            => ':attribute必须是yes、on或者1',
    ':attribute not a valid datetime'                           => ':attribute不是一个有效的日期或时间格式',
    ':attribute not a valid file'                               => ':attribute不是有效的上传文件',
    ':attribute not a valid image'                              => ':attribute不是有效的图像文件',
    ':attribute must be alpha'                                  => ':attribute只能是字母',
    ':attribute must be alpha-numeric'                          => ':attribute只能是字母和数字',
    ':attribute must be alpha-numeric, dash, underscore'        => ':attribute只能是字母、数字和下划线_及破折号-',
    ':attribute not a valid domain or ip'                       => ':attribute不是有效的域名或者IP',
    ':attribute must be chinese'                                => ':attribute只能是汉字',
    ':attribute must be chinese or alpha'                       => ':attribute只能是汉字、字母',
    ':attribute must be chinese,alpha-numeric'                  => ':attribute只能是汉字、字母和数字',
    ':attribute must be chinese,alpha-numeric,underscore, dash' => ':attribute只能是汉字、字母、数字和下划线_及破折号-',
    ':attribute not a valid url'                                => ':attribute不是有效的URL地址',
    ':attribute not a valid ip'                                 => ':attribute不是有效的IP地址',
    ':attribute must be dateFormat of :rule'                    => ':attribute必须使用日期格式 :rule',
    ':attribute must be in :rule'                               => ':attribute必须在 :rule 范围内',
    ':attribute be notin :rule'                                 => ':attribute不能在 :rule 范围内',
    ':attribute must between :1 - :2'                           => ':attribute只能在 :1 - :2 之间',
    ':attribute not between :1 - :2'                            => ':attribute不能在 :1 - :2 之间',
    'size of :attribute must be :rule'                          => ':attribute长度不符合要求 :rule',
    'max size of :attribute must be :rule'                      => ':attribute长度不能超过 :rule',
    'min size of :attribute must be :rule'                      => ':attribute长度不能小于 :rule',
    ':attribute cannot be less than :rule'                      => ':attribute日期不能小于 :rule',
    ':attribute cannot exceed :rule'                            => ':attribute日期不能超过 :rule',
    ':attribute not within :rule'                               => '不在有效期内 :rule',
    'access IP is not allowed'                                  => '不允许的IP访问',
    'access IP denied'                                          => '禁止的IP访问',
    ':attribute out of accord with :2'                          => ':attribute和确认字段:2不一致',
    ':attribute cannot be same with :2'                         => ':attribute和比较字段:2不能相同',
    ':attribute must greater than or equal :rule'               => ':attribute必须大于等于 :rule',
    ':attribute must greater than :rule'                        => ':attribute必须大于 :rule',
    ':attribute must less than or equal :rule'                  => ':attribute必须小于等于 :rule',
    ':attribute must less than :rule'                           => ':attribute必须小于 :rule',
    ':attribute must equal :rule'                               => ':attribute必须等于 :rule',
    ':attribute has exists'                                     => ':attribute已存在',
    ':attribute not conform to the rules'                       => ':attribute不符合指定规则',
    'invalid Request method'                                    => '无效的请求类型',
    'invalid token'                                             => '令牌数据无效',
    'not conform to the rules'                                  => '规则错误',
];
