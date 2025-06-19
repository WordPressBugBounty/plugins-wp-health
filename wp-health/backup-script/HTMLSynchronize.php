<?php

if (!class_exists('UmbrellaHTMLSynchronize', false)):
    class UmbrellaHTMLSynchronize
    {
        public function render($str = '')
        {
            ?>
            <!doctype html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport"
                    content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
                <meta http-equiv="X-UA-Compatible" content="ie=edge">
                <title>Synchronize</title>
                <style>
                    body {
                        color: #333;
                        margin: 0;
                        height: 100vh;
                        background-color: #4f46e5;
                        font-family: "Open Sans", "Helvetica Neue", Helvetica, Arial, sans-serif;
                    }
                    .content {
                        display:flex;
                        align-items: center;
                        justify-content: center;

                    }

                    .box{
                        margin-top: 32px;
                        background-color: #fff;
                        padding:16px;
                        max-width: 600px;
                        border-radius: 16px;
                    }


                </style>
            </head>
            <body>

            <div class="content">
                <div class="box">
                    <p>
                        A process is running in the background to synchronize your website
                    </p>
                    <p>This file will be deleted when the process is finished</p>
					<p><?php echo $str; ?></p>
                </div>
            </div>


            </body>
            </html>
<?php
die;
        }
    }
endif;
